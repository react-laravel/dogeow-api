<?php

namespace Tests\Feature\Controllers\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameSkillDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Hero' . $user->id,
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 10,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'available_stat_points' => 0,
            'skill_points' => 5,
            'current_hp' => 100,
            'current_mana' => 50,
            'current_map_id' => 1,
            'is_fighting' => false,
            'difficulty_tier' => 1,
        ], $attributes));
    }

    private function createSkillDefinition(array $attributes = []): GameSkillDefinition
    {
        return GameSkillDefinition::create(array_merge([
            'name' => 'Basic Attack',
            'description' => 'A basic attack skill',
            'skill_type' => 'attack',
            'effect_key' => 'basic_attack',
            'class_restriction' => 'all',
            'skill_points_cost' => 1,
            'required_level' => 1,
            'cooldown' => 0,
            'mana_cost' => 0,
            'is_active' => true,
        ], $attributes));
    }

    public function test_can_get_skills(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $this->createSkillDefinition();

        $response = $this->actingAs($user)
            ->getJson('/api/rpg/skills?character_id=' . $character->id);

        $response->assertStatus(200);
    }

    public function test_can_learn_skill(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['skill_points' => 5]);
        $skillDef = $this->createSkillDefinition();

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/skills/learn?character_id=' . $character->id, [
                'skill_id' => $skillDef->id,
            ]);

        $response->assertStatus(200);
    }

    public function test_fails_to_learn_skill_without_enough_skill_points(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['skill_points' => 0]);
        $skillDef = $this->createSkillDefinition(['skill_points_cost' => 5]);

        $response = $this->actingAs($user)
            ->postJson('/api/rpg/skills/learn?character_id=' . $character->id, [
                'skill_id' => $skillDef->id,
            ]);

        // May return 400 or 422 depending on validation
        $this->assertContains($response->status(), [400, 422]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/rpg/skills');

        $response->assertStatus(401);
    }
}
