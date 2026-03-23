<?php

namespace App\Services\SystemStatus;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class GithubRateLimitChecker
{
    /**
     * @return array{
     *   status: string,
     *   details: string,
     *   core_remaining?: int|null,
     *   core_limit?: int|null,
     *   core_used?: int|null,
     *   graphql_remaining?: int|null,
     *   graphql_limit?: int|null,
     *   graphql_used?: int|null,
     *   reset_at?: string|null
     * }
     */
    public function check(): array
    {
        $token = config('services.github.token');

        if (! $token) {
            return [
                'status' => 'warning',
                'details' => '未配置 GitHub PAT，无法读取 API 配额状态',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'DogeOW System Status',
                ])
                ->get('https://api.github.com/rate_limit');

            if ($response->failed()) {
                return [
                    'status' => 'error',
                    'details' => 'GitHub API 配额读取失败: HTTP ' . $response->status(),
                ];
            }

            $coreLimit = Arr::get($response->json(), 'resources.core.limit');
            $coreRemaining = Arr::get($response->json(), 'resources.core.remaining');
            $coreUsed = Arr::get($response->json(), 'resources.core.used');
            $graphqlLimit = Arr::get($response->json(), 'resources.graphql.limit');
            $graphqlRemaining = Arr::get($response->json(), 'resources.graphql.remaining');
            $graphqlUsed = Arr::get($response->json(), 'resources.graphql.used');
            $resetAt = Arr::get($response->json(), 'resources.core.reset');

            $status = 'online';
            if (is_int($coreLimit) && is_int($coreRemaining) && $coreLimit > 0) {
                $ratio = $coreRemaining / $coreLimit;
                if ($ratio <= 0.1) {
                    $status = 'error';
                } elseif ($ratio <= 0.2) {
                    $status = 'warning';
                }
            }

            $details = sprintf(
                '一小时 %s/%s，已用 %s；GraphQL %s/%s，已用 %s',
                $coreRemaining ?? '-',
                $coreLimit ?? '-',
                $coreUsed ?? '-',
                $graphqlRemaining ?? '-',
                $graphqlLimit ?? '-',
                $graphqlUsed ?? '-'
            );

            return [
                'status' => $status,
                'details' => $details,
                'core_remaining' => is_int($coreRemaining) ? $coreRemaining : null,
                'core_limit' => is_int($coreLimit) ? $coreLimit : null,
                'core_used' => is_int($coreUsed) ? $coreUsed : null,
                'graphql_remaining' => is_int($graphqlRemaining) ? $graphqlRemaining : null,
                'graphql_limit' => is_int($graphqlLimit) ? $graphqlLimit : null,
                'graphql_used' => is_int($graphqlUsed) ? $graphqlUsed : null,
                'reset_at' => is_int($resetAt) ? date(DATE_ATOM, $resetAt) : null,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'details' => 'GitHub API 配额读取失败: ' . $e->getMessage(),
            ];
        }
    }
}
