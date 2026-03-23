<?php

namespace Tests\Feature\Controllers;

use App\Models\Repo\WatchedPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_refreshes_watched_packages_for_matching_repository(): void
    {
        Config::set('services.github.webhook_secret', 'secret123');

        $user = User::factory()->create();

        $package = WatchedPackage::query()->create([
            'user_id' => $user->id,
            'source_provider' => 'github',
            'source_owner' => 'vercel',
            'source_repo' => 'next.js',
            'source_url' => 'https://github.com/vercel/next.js',
            'ecosystem' => 'npm',
            'package_name' => 'react',
            'manifest_path' => 'package.json',
            'current_version_constraint' => '^18.2.0',
            'normalized_current_version' => '18.2.0',
            'latest_version' => null,
            'watch_level' => 'major',
        ]);

        Http::fake([
            'https://registry.npmjs.org/react' => Http::response([
                'dist-tags' => ['latest' => '19.1.0'],
            ], 200),
        ]);

        $payload = [
            'repository' => [
                'name' => 'next.js',
                'owner' => ['login' => 'vercel'],
            ],
        ];
        $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'secret123');

        $response = $this->postJson('/api/github/webhooks/repo-watch', $payload, [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => $signature,
        ]);

        $response->assertOk()
            ->assertJsonPath('refreshed_packages', 1);

        $package->refresh();
        $this->assertSame('19.1.0', $package->latest_version);
        $this->assertSame('major', $package->latest_update_type);
    }

    public function test_webhook_validates_signature_when_secret_is_configured(): void
    {
        Config::set('services.github.webhook_secret', 'secret123');

        $payload = json_encode([
            'repository' => [
                'name' => 'next.js',
                'owner' => ['login' => 'vercel'],
            ],
        ], JSON_THROW_ON_ERROR);

        $invalidSignature = 'sha256=' . hash_hmac('sha256', $payload, 'wrong-secret');

        $this->call('POST', '/api/github/webhooks/repo-watch', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_HUB_SIGNATURE_256' => $invalidSignature,
        ], $payload)->assertUnauthorized();
    }

    public function test_webhook_returns_service_unavailable_when_secret_is_not_configured(): void
    {
        Config::set('services.github.webhook_secret', null);

        $this->postJson('/api/github/webhooks/repo-watch', [
            'repository' => [
                'name' => 'next.js',
                'owner' => ['login' => 'vercel'],
            ],
        ], [
            'X-GitHub-Event' => 'push',
        ])->assertStatus(503);
    }
}
