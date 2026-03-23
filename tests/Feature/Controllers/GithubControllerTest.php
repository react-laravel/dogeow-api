<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class GithubControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_returns_github_oauth_url(): void
    {
        // Use partial mock to add methods to driver
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();
        $redirect = Mockery::mock();
        $redirect->shouldReceive('getTargetUrl')
            ->andReturn('https://github.com/login/oauth/authorize?client_id=test');

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('scopes')->andReturnSelf();
        $driver->shouldReceive('redirect')->andReturn($redirect);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        $response = $this->getJson('/api/auth/github');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success', 'message', 'data' => ['url'],
        ]);
    }

    public function test_callback_creates_new_user(): void
    {
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = 'github123';
        $githubUser->name = 'Test User';
        $githubUser->email = 'test@example.com';
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/123';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000/auth/github/callback');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'github_id' => 'github123',
            'email' => 'test@example.com',
        ]);
    }

    public function test_callback_existing_user_returns_token(): void
    {
        $user = User::factory()->create([
            'github_id' => 'github123',
        ]);

        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = 'github123';
        $githubUser->name = 'Test User';
        $githubUser->email = $user->email;
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/123';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000/auth/github/callback');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();
        $response->assertRedirectContains('token=');
    }

    public function test_callback_uses_nickname_when_name_is_null(): void
    {
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = 'github999';
        $githubUser->name = null;
        $githubUser->nickname = 'Nick Name';
        $githubUser->email = 'nick@example.com';
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/999';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000/auth/github/callback');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'github_id' => 'github999',
            'name' => 'Nick Name',
            'email' => 'nick@example.com',
        ]);
    }

    public function test_callback_keeps_redirect_url_when_no_callback_suffix(): void
    {
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = 'github777';
        $githubUser->name = 'Redirect User';
        $githubUser->nickname = 'redirect-user';
        $githubUser->email = 'redirect@example.com';
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/777';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();
        $this->assertStringStartsWith('http://localhost:3000?token=', $response->headers->get('Location') ?? '');
    }

    public function test_callback_strips_callback_suffix_from_redirect_url(): void
    {
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = 'github555';
        $githubUser->name = 'Strip User';
        $githubUser->nickname = 'strip-user';
        $githubUser->email = 'strip@example.com';
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/555';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000/auth/github/callback');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        $this->assertStringStartsWith('http://localhost:3000?token=', $location);
        $this->assertStringNotContainsString('/auth/github/callback?token=', $location);
    }

    public function test_callback_handles_null_github_id_branch(): void
    {
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = null;
        $githubUser->name = 'Null Id User';
        $githubUser->nickname = 'null-id-user';
        $githubUser->email = 'null-id@example.com';
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/0';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000/auth/github/callback');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();
        $response->assertRedirectContains('token=');
        $this->assertDatabaseHas('users', [
            'email' => 'null-id@example.com',
            'name' => 'Null Id User',
        ]);
    }

    public function test_callback_redirect_contains_decodable_user_query_param(): void
    {
        $driver = Mockery::mock(\Laravel\Socialite\Two\GithubProvider::class)->makePartial();

        $githubUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $githubUser->id = 'github888';
        $githubUser->name = 'Query User';
        $githubUser->nickname = 'query-user';
        $githubUser->email = 'query@example.com';
        $githubUser->avatar = 'https://avatars.githubusercontent.com/u/888';

        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($githubUser);

        $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) use ($driver) {
            $mock->shouldReceive('driver')->with('github')->andReturn($driver);
        });

        Config::set('services.github.redirect', 'http://localhost:3000/auth/github/callback');

        $response = $this->get('/api/auth/github/callback');

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';

        $query = parse_url($location, PHP_URL_QUERY);
        parse_str($query ?? '', $params);

        $this->assertArrayHasKey('token', $params);
        $this->assertNotEmpty($params['token']);
        $this->assertArrayHasKey('user', $params);

        $decodedUser = json_decode($params['user'], true);
        $this->assertIsArray($decodedUser);
        $this->assertSame('query@example.com', $decodedUser['email'] ?? null);
    }
}
