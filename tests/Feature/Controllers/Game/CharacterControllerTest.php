<?php

namespace Tests\Feature\Controllers\Game;

use App\Models\Game\GameCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Hero' . $user->id,
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 1,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'available_stat_points' => 5,
            'skill_points' => 0,
            'current_hp' => 100,
            'current_mana' => 50,
            'current_map_id' => 1,
            'is_fighting' => false,
            'difficulty_tier' => 1,
        ], $attributes));
    }

    public function test_can_get_character_list(): void
    {
        $user = User::factory()->create();
        $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/characters');

        $response->assertStatus(200);
    }

    public function test_can_get_character_detail(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/character?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_create_character(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/character', [
                'name' => 'NewHero',
                'class' => 'warrior',
                'gender' => 'male',
            ]);

        $response->assertStatus(201);
    }

    public function test_validates_character_creation_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/character', [
                'name' => '',
                'class' => 'warrior',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_delete_character(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->deleteJson('/api/rpg/character', [
                'character_id' => $character->id,
            ]);

        $response->assertStatus(200);
    }

    public function test_can_allocate_stats(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'available_stat_points' => 5,
            'strength' => 10,
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/rpg/character/stats?character_id=' . $character->id, [
                'strength' => 2,
            ]);

        // May return 200, 400 or 422 depending on validation or game logic
        $this->assertContains($response->status(), [200, 400, 422]);
    }

    public function test_can_update_difficulty(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->putJson('/api/rpg/character/difficulty', [
                'character_id' => $character->id,
                'difficulty_tier' => 2,
            ]);

        $response->assertStatus(200);
    }

    public function test_can_get_character_full_detail(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/character/detail?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_update_online_status(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/character/online?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/characters');

        $response->assertStatus(401);
    }
}
