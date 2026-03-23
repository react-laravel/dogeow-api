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
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/characters');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'characters' => [
                        '*' => [
                            'id',
                            'name',
                            'class',
                            'level',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_get_character_detail(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/character?character_id=' . $character->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'character' => [
                        'id',
                        'name',
                        'class',
                        'level',
                    ],
                    'experience_table',
                    'combat_stats',
                    'stats_breakdown',
                    'equipped_items',
                    'current_hp',
                    'current_mana',
                ],
            ]);
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

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '角色创建成功',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'character' => [
                        'id',
                        'name',
                        'class',
                    ],
                    'combat_stats',
                    'stats_breakdown',
                    'current_hp',
                    'current_mana',
                ],
            ]);

        $this->assertDatabaseHas('game_characters', [
            'user_id' => $user->id,
            'name' => 'NewHero',
            'class' => 'warrior',
        ]);
    }

    public function test_validates_character_creation_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/character', [
                'name' => '',
                'class' => 'warrior',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    public function test_can_delete_character(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->deleteJson('/api/rpg/character', [
                'character_id' => $character->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '角色已删除',
            ]);

        $this->assertDatabaseMissing('game_characters', [
            'id' => $character->id,
        ]);
    }

    public function test_can_allocate_stats(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, [
            'stat_points' => 5,
            'strength' => 10,
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/rpg/character/stats', [
                'character_id' => $character->id,
                'strength' => 2,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'character',
                ],
            ]);
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

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '难度已更新',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'character' => [
                        'id',
                        'difficulty_tier',
                    ],
                ],
            ]);

        $this->assertEquals(2, $character->fresh()->difficulty_tier);
    }

    public function test_can_get_character_full_detail(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/character/detail?character_id=' . $character->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    public function test_can_update_online_status(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/character/online?character_id=' . $character->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'last_online',
                ],
            ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/characters');

        $response->assertStatus(401);
    }
}
