<?php

namespace Tests\Unit\Services\Github;

use App\Models\Repo\WatchedRepository;
use App\Models\User;
use App\Services\Github\GithubRepositoryWatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class GithubRepositoryWatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    private GithubRepositoryWatcherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GithubRepositoryWatcherService;
    }

    #[Test]
    public function parse_github_url_extracts_owner_and_repo_from_github_com_url(): void
    {
        // Arrange
        $url = 'https://github.com/owner/repo';

        // Act
        $result = $this->service->parseGithubUrl($url);

        // Assert
        $this->assertEquals(['owner', 'repo'], $result);
    }

    #[Test]
    public function parse_github_url_extracts_owner_and_repo_from_github_com_url_with_git_suffix(): void
    {
        // Arrange
        $url = 'https://github.com/owner/repo.git';

        // Act
        $result = $this->service->parseGithubUrl($url);

        // Assert
        $this->assertEquals(['owner', 'repo'], $result);
    }

    #[Test]
    public function parse_github_url_extracts_owner_and_repo_from_www_github_com_url(): void
    {
        // Arrange
        $url = 'https://www.github.com/owner/repo';

        // Act
        $result = $this->service->parseGithubUrl($url);

        // Assert
        $this->assertEquals(['owner', 'repo'], $result);
    }

    #[Test]
    public function parse_github_url_throws_exception_for_invalid_url(): void
    {
        // Arrange
        $url = 'https://gitlab.com/owner/repo';

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('请输入有效的 GitHub 仓库地址');
        $this->service->parseGithubUrl($url);
    }

    #[Test]
    public function parse_github_url_throws_exception_when_owner_repo_not_found(): void
    {
        // Arrange
        $url = 'https://github.com/';

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('无法从地址中识别仓库 owner/repo');
        $this->service->parseGithubUrl($url);
    }

    #[Test]
    public function sync_from_url_creates_watched_repository(): void
    {
        // Arrange
        $user = User::factory()->create();
        Http::fake([
            'api.github.com/repos/owner/repo' => Http::response([
                'full_name' => 'owner/repo',
                'html_url' => 'https://github.com/owner/repo',
                'default_branch' => 'main',
                'language' => 'PHP',
                'description' => 'Test repository',
                'pushed_at' => '2024-01-01T00:00:00Z',
                'stargazers_count' => 100,
                'open_issues_count' => 5,
                'forks_count' => 10,
            ], 200),
            'api.github.com/repos/owner/repo/contents/package.json' => Http::response([
                'content' => base64_encode('{"name": "test-package", "version": "1.0.0"}'),
            ], 200),
            'api.github.com/repos/owner/repo/releases/latest' => Http::response([], 404),
            'api.github.com/repos/owner/repo/tags*' => Http::response([], 404),
        ]);

        // Act
        $result = $this->service->syncFromUrl($user, 'https://github.com/owner/repo');

        // Assert
        $this->assertInstanceOf(WatchedRepository::class, $result);
        $this->assertEquals('owner', $result->owner);
        $this->assertEquals('repo', $result->repo);
        $this->assertEquals('owner/repo', $result->full_name);
        $this->assertEquals('github', $result->provider);
        $this->assertEquals($user->id, $result->user_id);
    }

    #[Test]
    public function sync_from_url_returns_existing_watched_repository(): void
    {
        // Arrange
        $user = User::factory()->create();
        $existingRepo = WatchedRepository::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'owner' => 'existing',
            'repo' => 'repo',
            'full_name' => 'existing/repo',
            'html_url' => 'https://github.com/existing/repo',
        ]);

        Http::fake([
            'api.github.com/repos/existing/repo' => Http::response([
                'full_name' => 'existing/repo',
                'html_url' => 'https://github.com/existing/repo',
                'default_branch' => 'main',
                'language' => 'JavaScript',
                'description' => 'Existing repo',
                'pushed_at' => '2024-01-01T00:00:00Z',
                'stargazers_count' => 50,
                'open_issues_count' => 3,
                'forks_count' => 5,
            ], 200),
            'api.github.com/repos/existing/repo/contents/package.json' => Http::response([], 404),
            'api.github.com/repos/existing/repo/contents/composer.json' => Http::response([], 404),
            'api.github.com/repos/existing/repo/releases/latest' => Http::response([], 404),
            'api.github.com/repos/existing/repo/tags*' => Http::response([], 404),
        ]);

        // Act
        $result = $this->service->syncFromUrl($user, 'https://github.com/existing/repo');

        // Assert
        $this->assertEquals($existingRepo->id, $result->id);
        $this->assertEquals('existing', $result->owner);
    }

    #[Test]
    public function refresh_updates_existing_watched_repository(): void
    {
        // Arrange
        $user = User::factory()->create();
        $repo = WatchedRepository::create([
            'user_id' => $user->id,
            'provider' => 'github',
            'owner' => 'test',
            'repo' => 'refresh',
            'full_name' => 'test/refresh',
            'html_url' => 'https://github.com/test/refresh',
            'last_checked_at' => now()->subDay(),
        ]);

        Http::fake([
            'api.github.com/repos/test/refresh' => Http::response([
                'full_name' => 'test/refresh',
                'html_url' => 'https://github.com/test/refresh',
                'default_branch' => 'develop',
                'language' => 'Python',
                'description' => 'Updated description',
                'pushed_at' => '2024-06-01T00:00:00Z',
                'stargazers_count' => 200,
                'open_issues_count' => 10,
                'forks_count' => 20,
            ], 200),
            'api.github.com/repos/test/refresh/contents/package.json' => Http::response([
                'content' => base64_encode('{"name": "updated-package", "version": "2.0.0"}'),
            ], 200),
            'api.github.com/repos/test/refresh/releases/latest' => Http::response([
                'id' => 2,
                'tag_name' => 'v2.0.0',
                'name' => 'Version 2.0.0',
                'body' => 'Release notes',
                'html_url' => 'https://github.com/test/refresh/releases/tag/v2.0.0',
                'published_at' => '2024-06-01T00:00:00Z',
                'draft' => false,
                'prerelease' => false,
            ], 200),
        ]);

        // Act
        $result = $this->service->refresh($repo);

        // Assert
        $this->assertEquals('test/refresh', $result->full_name);
        $this->assertEquals('develop', $result->default_branch);
        $this->assertEquals('Python', $result->language);
        $this->assertNotNull($result->last_checked_at);
    }

    #[Test]
    public function sync_repository_fetches_manifest_info(): void
    {
        // Arrange
        $user = User::factory()->create();
        Http::fake([
            'api.github.com/repos/manifest/repo' => Http::response([
                'full_name' => 'manifest/repo',
                'html_url' => 'https://github.com/manifest/repo',
                'default_branch' => 'main',
                'language' => 'PHP',
                'description' => 'Manifest test',
                'pushed_at' => '2024-01-01T00:00:00Z',
                'stargazers_count' => 10,
                'open_issues_count' => 1,
                'forks_count' => 2,
            ], 200),
            'api.github.com/repos/manifest/repo/contents/package.json' => Http::response([
                'content' => base64_encode('{"name": "my-package", "version": "1.0.0", "dependencies": {}}'),
            ], 200),
            'api.github.com/repos/manifest/repo/contents/composer.json' => Http::response([], 404),
            'api.github.com/repos/manifest/repo/releases/latest' => Http::response([], 404),
            'api.github.com/repos/manifest/repo/tags*' => Http::response([], 404),
        ]);

        // Act
        $result = $this->service->syncFromUrl($user, 'https://github.com/manifest/repo');

        // Assert
        $this->assertEquals('npm', $result->ecosystem);
        $this->assertEquals('my-package', $result->package_name);
        $this->assertEquals('package.json', $result->manifest_path);
    }

    #[Test]
    public function sync_repository_throws_exception_when_api_fails(): void
    {
        // Arrange
        $user = User::factory()->create();
        Http::fake([
            'api.github.com/repos/nonexistent/repo' => Http::response(['message' => 'Not Found'], 404),
        ]);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('读取 GitHub 仓库信息失败，请确认仓库存在且可公开访问');
        $this->service->syncFromUrl($user, 'https://github.com/nonexistent/repo');
    }
}
