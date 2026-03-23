<?php

namespace App\Services\Github;

use App\Models\Repo\RepositoryUpdate;
use App\Models\Repo\WatchedRepository;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GithubRepositoryWatcherService
{
    public function syncFromUrl(User $user, string $url): WatchedRepository
    {
        [$owner, $repo] = $this->parseGithubUrl($url);

        $watchedRepository = WatchedRepository::firstOrNew([
            'user_id' => $user->id,
            'provider' => 'github',
            'owner' => $owner,
            'repo' => $repo,
        ]);

        return $this->syncRepository($watchedRepository);
    }

    public function refresh(WatchedRepository $watchedRepository): WatchedRepository
    {
        return $this->syncRepository($watchedRepository);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function parseGithubUrl(string $url): array
    {
        $trimmed = trim($url);
        $path = parse_url($trimmed, PHP_URL_PATH);
        $host = strtolower((string) parse_url($trimmed, PHP_URL_HOST));

        if (! $path || ! in_array($host, ['github.com', 'www.github.com'], true)) {
            throw new RuntimeException('请输入有效的 GitHub 仓库地址');
        }

        $parts = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($parts) < 2) {
            throw new RuntimeException('无法从地址中识别仓库 owner/repo');
        }

        return [$parts[0], preg_replace('/\.git$/', '', $parts[1]) ?: $parts[1]];
    }

    private function syncRepository(WatchedRepository $watchedRepository): WatchedRepository
    {
        $repoApi = sprintf(
            'https://api.github.com/repos/%s/%s',
            rawurlencode($watchedRepository->owner),
            rawurlencode($watchedRepository->repo)
        );

        $repoResponse = $this->githubApi()->get($repoApi);
        if ($repoResponse->failed()) {
            throw new RuntimeException('读取 GitHub 仓库信息失败，请确认仓库存在且可公开访问');
        }

        $repo = $repoResponse->json();
        $manifest = $this->fetchManifest($repoApi);
        $release = $this->fetchLatestRelease($repoApi);
        $latestVersion = $release['version'] ?? $manifest['version'] ?? null;
        $latestPublishedAt = $release['published_at'] ?? Arr::get($repo, 'pushed_at');

        $watchedRepository->fill([
            'full_name' => Arr::get($repo, 'full_name'),
            'html_url' => Arr::get($repo, 'html_url'),
            'default_branch' => Arr::get($repo, 'default_branch'),
            'language' => Arr::get($repo, 'language'),
            'ecosystem' => $manifest['ecosystem'],
            'package_name' => $manifest['package_name'],
            'manifest_path' => $manifest['path'],
            'latest_version' => $latestVersion,
            'latest_source_type' => $release['source_type'] ?? ($manifest['path'] ? 'manifest' : null),
            'latest_release_url' => $release['url'] ?? Arr::get($repo, 'html_url'),
            'description' => Arr::get($repo, 'description'),
            'latest_release_published_at' => $latestPublishedAt ? Carbon::parse($latestPublishedAt) : null,
            'last_checked_at' => now(),
            'last_error' => null,
            'metadata' => [
                'stars' => Arr::get($repo, 'stargazers_count'),
                'open_issues' => Arr::get($repo, 'open_issues_count'),
                'forks' => Arr::get($repo, 'forks_count'),
            ],
        ]);
        $watchedRepository->save();

        $this->storeLatestUpdate($watchedRepository, $release, $manifest);

        return $watchedRepository->fresh(['latestUpdate', 'updates']);
    }

    /**
     * @return array{ecosystem: ?string, package_name: ?string, version: ?string, path: ?string, raw: array<string, mixed>}
     */
    private function fetchManifest(string $repoApi): array
    {
        foreach (['package.json' => 'npm', 'composer.json' => 'composer'] as $path => $ecosystem) {
            $raw = $this->fetchManifestFile($repoApi, $path);
            if (! $raw) {
                continue;
            }

            return [
                'ecosystem' => $ecosystem,
                'package_name' => Arr::get($raw, 'name'),
                'version' => Arr::get($raw, 'version'),
                'path' => $path,
                'raw' => $raw,
            ];
        }

        return [
            'ecosystem' => null,
            'package_name' => null,
            'version' => null,
            'path' => null,
            'raw' => [],
        ];
    }

    /**
     * @return array{source_type: string, source_id: string, version: ?string, title: ?string, body: ?string, url: ?string, published_at: ?string, metadata: array<string, mixed>}|null
     */
    private function fetchLatestRelease(string $repoApi): ?array
    {
        $releaseResponse = $this->githubApi()->get($repoApi . '/releases/latest');

        if ($releaseResponse->ok()) {
            $release = $releaseResponse->json();

            return [
                'source_type' => 'release',
                'source_id' => (string) Arr::get($release, 'id'),
                'version' => Arr::get($release, 'tag_name'),
                'title' => Arr::get($release, 'name') ?: Arr::get($release, 'tag_name'),
                'body' => Arr::get($release, 'body'),
                'url' => Arr::get($release, 'html_url'),
                'published_at' => Arr::get($release, 'published_at') ?: Arr::get($release, 'created_at'),
                'metadata' => [
                    'draft' => Arr::get($release, 'draft', false),
                    'prerelease' => Arr::get($release, 'prerelease', false),
                ],
            ];
        }

        $tagResponse = $this->githubApi()->get($repoApi . '/tags?per_page=1');
        $tags = $tagResponse->ok() && is_array($tagResponse->json()) ? $tagResponse->json() : [];
        if (count($tags) > 0) {
            $tag = $tags[0];

            return [
                'source_type' => 'tag',
                'source_id' => (string) Arr::get($tag, 'commit.sha', Arr::get($tag, 'name', 'latest')),
                'version' => Arr::get($tag, 'name'),
                'title' => Arr::get($tag, 'name'),
                'body' => null,
                'url' => null,
                'published_at' => null,
                'metadata' => [
                    'commit_sha' => Arr::get($tag, 'commit.sha'),
                ],
            ];
        }

        return null;
    }

    /**
     * @param  array{source_type: string, source_id: string, version: ?string, title: ?string, body: ?string, url: ?string, published_at: ?string, metadata: array<string, mixed>}|null  $release
     * @param  array{ecosystem: ?string, package_name: ?string, version: ?string, path: ?string, raw: array<string, mixed>}  $manifest
     */
    private function storeLatestUpdate(
        WatchedRepository $watchedRepository,
        ?array $release,
        array $manifest
    ): void {
        if ($release) {
            RepositoryUpdate::updateOrCreate(
                [
                    'watched_repository_id' => $watchedRepository->id,
                    'source_type' => $release['source_type'],
                    'source_id' => $release['source_id'],
                ],
                [
                    'version' => $release['version'],
                    'title' => $release['title'],
                    'release_url' => $release['url'],
                    'body' => $release['body'],
                    'ai_summary' => $this->buildAiSummary(
                        $watchedRepository->full_name,
                        $release['version'],
                        $release['body']
                    ),
                    'published_at' => $release['published_at'] ? Carbon::parse($release['published_at']) : now(),
                    'metadata' => $release['metadata'],
                ]
            );

            return;
        }

        if (! $manifest['version']) {
            return;
        }

        RepositoryUpdate::updateOrCreate(
            [
                'watched_repository_id' => $watchedRepository->id,
                'source_type' => 'manifest',
                'source_id' => (string) $manifest['version'],
            ],
            [
                'version' => $manifest['version'],
                'title' => $manifest['package_name'] ?: $watchedRepository->full_name,
                'release_url' => $watchedRepository->html_url,
                'body' => null,
                'ai_summary' => sprintf(
                    '检测到 %s 的清单版本为 %s，当前还没有公开 release note。',
                    $manifest['package_name'] ?: $watchedRepository->full_name,
                    $manifest['version']
                ),
                'published_at' => now(),
                'metadata' => [
                    'manifest_path' => $manifest['path'],
                    'ecosystem' => $manifest['ecosystem'],
                ],
            ]
        );
    }

    private function buildAiSummary(string $fullName, ?string $version, ?string $body): string
    {
        $versionText = $version ? " {$version}" : '';
        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags(Str::markdown($body ?? ''))) ?? '');

        if ($plainText === '') {
            return "检测到 {$fullName}{$versionText} 的最新更新，原仓库尚未提供详细发布说明。";
        }

        return sprintf(
            '检测到 %s%s 更新。摘要：%s',
            $fullName,
            $versionText,
            mb_substr($plainText, 0, 140)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchManifestFile(string $repoApi, string $path): ?array
    {
        try {
            $response = $this->githubApi()->get($repoApi . '/contents/' . $path);
        } catch (Throwable) {
            return null;
        }

        if ($response->status() === 404 || $response->failed()) {
            return null;
        }

        $payload = $response->json();
        $decoded = base64_decode((string) Arr::get($payload, 'content'), true);
        if ($decoded === false) {
            return null;
        }

        $raw = json_decode($decoded, true);

        return is_array($raw) ? $raw : null;
    }

    public function githubApi(): PendingRequest
    {
        $token = config('services.github.token');

        $client = Http::timeout(15)
            ->acceptJson()
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'DogeOW Repo Watcher',
            ]);

        if ($token) {
            $client = $client->withToken($token);
        }

        return $client;
    }
}
