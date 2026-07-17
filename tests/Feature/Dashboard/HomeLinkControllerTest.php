<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_home_links(): void
    {
        $this->getJson('/api/dashboard/home-links')->assertStatus(401);
    }

    public function test_non_admin_cannot_list_home_links(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

        $this->getJson('/api/dashboard/home-links')
            ->assertStatus(403)
            ->assertJsonPath('message', '需要管理员权限');
    }

    public function test_admin_can_list_home_links_with_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $response = $this->getJson('/api/dashboard/home-links');

        $response->assertOk();

        $links = $response->json();
        $this->assertIsArray($links);
        $this->assertNotEmpty($links);

        foreach ($links as $link) {
            $this->assertIsArray($link);
            $this->assertArrayHasKey('id', $link);
            $this->assertArrayHasKey('label', $link);
            $this->assertArrayHasKey('caption', $link);
            $this->assertArrayHasKey('href', $link);
            $this->assertArrayHasKey('icon', $link);
            $this->assertArrayHasKey('gradientClassName', $link);
            $this->assertNotSame('', (string) $link['id']);
            $this->assertNotSame('', (string) $link['label']);
            $this->assertStringStartsWith('https://', (string) $link['href']);
        }

        $ids = array_column($links, 'id');
        $this->assertContains('game', $ids);
        $this->assertContains('mysql-compare', $ids);
        $this->assertContains('news', $ids);
        $this->assertSame(count($ids), count(array_unique($ids)));
    }
}
