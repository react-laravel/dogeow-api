<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Support\DashboardCacheRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CacheControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_can_list_dashboard_cache_items(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/cache');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => DashboardCacheRegistry::MUSIC_UPYUN_LIST,
            'cache_key' => 'musics:upyun:/music:v2',
        ]);
    }

    public function test_admin_can_clear_a_cache_item(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        Cache::put('musics:upyun:/music:v2', ['demo' => true], now()->addHour());

        $response = $this->deleteJson('/api/cache/' . DashboardCacheRegistry::MUSIC_UPYUN_LIST);

        $response->assertOk()
            ->assertJsonPath('id', DashboardCacheRegistry::MUSIC_UPYUN_LIST)
            ->assertJsonPath('had_value', true);

        $this->assertFalse(Cache::has('musics:upyun:/music:v2'));
    }

    public function test_non_admin_cannot_manage_dashboard_cache(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/cache');

        $response->assertStatus(403)
            ->assertJsonPath('message', '需要管理员权限');
    }
}
