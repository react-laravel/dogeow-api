<?php

namespace App\Services\Packages;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PackageRegistryService
{
    /**
     * @return array{latest_version: ?string, registry_url: ?string, update_type: ?string}
     */
    public function resolveLatest(string $ecosystem, string $packageName, ?string $currentVersion): array
    {
        $resolved = $this->resolveLatestMany([
            [
                'ecosystem' => $ecosystem,
                'package_name' => $packageName,
                'current_version' => $currentVersion,
            ],
        ]);

        return $resolved[$this->resultKey($ecosystem, $packageName, $currentVersion)] ?? $this->emptyResult();
    }

    /**
     * @param  array<int, array{ecosystem: string, package_name: string, current_version: ?string}>  $packages
     * @return array<string, array{latest_version: ?string, registry_url: ?string, update_type: ?string}>
     */
    public function resolveLatestMany(array $packages): array
    {
        $results = [];
        $pending = [];

        foreach ($packages as $package) {
            $ecosystem = $package['ecosystem'];
            $packageName = $package['package_name'];
            $currentVersion = $package['current_version'] ?? null;
            $resultKey = $this->resultKey($ecosystem, $packageName, $currentVersion);
            $cacheKey = $this->cacheKey($ecosystem, $packageName);

            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $results[$resultKey] = [
                    'latest_version' => $cached['latest_version'] ?? null,
                    'registry_url' => $cached['registry_url'] ?? null,
                    'update_type' => $this->detectUpdateType($currentVersion, $cached['latest_version'] ?? null),
                ];

                continue;
            }

            $pending[$resultKey] = [
                'ecosystem' => $ecosystem,
                'package_name' => $packageName,
                'current_version' => $currentVersion,
                'cache_key' => $cacheKey,
            ];
        }

        if ($pending === []) {
            return $results;
        }

        $responses = Http::pool(function (Pool $pool) use ($pending) {
            $requests = [];

            foreach ($pending as $resultKey => $package) {
                $requests[$resultKey] = match ($package['ecosystem']) {
                    'npm' => $pool->as($resultKey)->timeout(10)->acceptJson()->get($this->npmUrl($package['package_name'])),
                    'composer' => $pool->as($resultKey)->timeout(10)->acceptJson()->get($this->composerUrl($package['package_name'])),
                    default => null,
                };
            }

            return array_filter($requests);
        });

        foreach ($pending as $resultKey => $package) {
            $response = $responses[$resultKey] ?? null;
            $payload = $response instanceof Response ? $response->json() : null;
            $failed = ! ($response instanceof Response) || $response->failed();

            $resolved = match ($package['ecosystem']) {
                'npm' => $this->mapNpmResponse($package['package_name'], $package['current_version'], $payload, $failed),
                'composer' => $this->mapComposerResponse($package['package_name'], $package['current_version'], $payload, $failed),
                default => $this->emptyResult(),
            };

            $results[$resultKey] = $resolved;

            Cache::put($package['cache_key'], [
                'latest_version' => $resolved['latest_version'],
                'registry_url' => $resolved['registry_url'],
            ], now()->addMinutes(30));
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{latest_version: ?string, registry_url: ?string, update_type: ?string}
     */
    private function mapNpmResponse(string $packageName, ?string $currentVersion, ?array $payload, bool $failed): array
    {
        if ($failed) {
            return ['latest_version' => null, 'registry_url' => "https://www.npmjs.com/package/{$packageName}", 'update_type' => null];
        }

        $latestVersion = Arr::get($payload, 'dist-tags.latest');

        return [
            'latest_version' => is_string($latestVersion) ? $latestVersion : null,
            'registry_url' => "https://www.npmjs.com/package/{$packageName}",
            'update_type' => $this->detectUpdateType($currentVersion, is_string($latestVersion) ? $latestVersion : null),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{latest_version: ?string, registry_url: ?string, update_type: ?string}
     */
    private function mapComposerResponse(string $packageName, ?string $currentVersion, ?array $payload, bool $failed): array
    {
        if ($failed) {
            return ['latest_version' => null, 'registry_url' => "https://packagist.org/packages/{$packageName}", 'update_type' => null];
        }

        $packages = Arr::get($payload, "packages.{$packageName}", []);
        $latestVersion = null;

        foreach ((array) $packages as $package) {
            $candidate = Arr::get($package, 'version_normalized') ?: Arr::get($package, 'version');
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = preg_replace('/\.0{5,}$/', '', $candidate) ?: $candidate;

            if ($latestVersion === null || version_compare($candidate, $latestVersion, '>')) {
                $latestVersion = $candidate;
            }
        }

        return [
            'latest_version' => $latestVersion,
            'registry_url' => "https://packagist.org/packages/{$packageName}",
            'update_type' => $this->detectUpdateType($currentVersion, $latestVersion),
        ];
    }

    private function cacheKey(string $ecosystem, string $packageName): string
    {
        return sprintf('repo-watch:registry:%s:%s', $ecosystem, strtolower($packageName));
    }

    private function resultKey(string $ecosystem, string $packageName, ?string $currentVersion): string
    {
        return implode(':', [$ecosystem, $packageName, $currentVersion ?? 'null']);
    }

    private function npmUrl(string $packageName): string
    {
        $encodedName = str_replace('%2F', '/', rawurlencode($packageName));

        return "https://registry.npmjs.org/{$encodedName}";
    }

    private function composerUrl(string $packageName): string
    {
        return 'https://repo.packagist.org/p2/' . str_replace('%2F', '/', rawurlencode($packageName)) . '.json';
    }

    /**
     * @return array{latest_version: ?string, registry_url: ?string, update_type: ?string}
     */
    private function emptyResult(): array
    {
        return [
            'latest_version' => null,
            'registry_url' => null,
            'update_type' => null,
        ];
    }

    private function detectUpdateType(?string $currentVersion, ?string $latestVersion): ?string
    {
        if (! $currentVersion || ! $latestVersion) {
            return null;
        }

        $current = $this->parseVersion($currentVersion);
        $latest = $this->parseVersion($latestVersion);

        if (! $current || ! $latest || version_compare($latestVersion, $currentVersion, '<=')) {
            return null;
        }

        if ($latest[0] !== $current[0]) {
            return 'major';
        }

        if ($latest[1] !== $current[1]) {
            return 'minor';
        }

        if ($latest[2] !== $current[2]) {
            return 'patch';
        }

        return null;
    }

    /**
     * @return array{0:int,1:int,2:int}|null
     */
    private function parseVersion(string $version): ?array
    {
        if (preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }
}
