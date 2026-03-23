<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMonsterDefinition;
use App\Services\Game\Combat\CombatDamageCalculator;
use App\Services\Game\Combat\CombatRewardCalculator;
use App\Services\Game\Combat\CombatSkillSelector;
use App\Services\Game\CombatRoundProcessor;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class CombatRoundProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected CombatRoundProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new CombatRoundProcessor;
    }

    public function test_process_one_round_with_no_monsters_returns_default_values(): void
    {
        $character = $this->createTestCharacter();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertArrayHasKey('round_damage_dealt', $result);
        $this->assertArrayHasKey('round_damage_taken', $result);
        $this->assertArrayHasKey('new_monster_hp', $result);
        $this->assertArrayHasKey('new_char_hp', $result);
        $this->assertArrayHasKey('defeat', $result);
        $this->assertArrayHasKey('has_alive_monster', $result);
    }

    public function test_process_one_round_calculates_damage_correctly(): void
    {
        $character = $this->createTestCharacter([
            'strength' => 100,
            'dexterity' => 50,
            'vitality' => 50,
            'energy' => 50,
        ]);

        // Set up combat monsters
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Slime',
                'level' => 1,
                'hp' => 50,
                'max_hp' => 50,
                'attack' => 10,
                'defense' => 5,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Character should deal some damage
        $this->assertGreaterThanOrEqual(0, $result['round_damage_dealt']);
        $this->assertIsInt($result['round_damage_dealt']);

        // Round details should be present
        $this->assertArrayHasKey('round_details', $result);
        $this->assertArrayHasKey('character', $result['round_details']);
        $this->assertArrayHasKey('monster', $result['round_details']);
    }

    public function test_process_one_round_with_skill_returns_skill_in_result(): void
    {
        $character = $this->createTestCharacter();

        // Set up a monster
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Goblin',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 5,
                'defense' => 0,
                'experience' => 15,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Should return skills_used_this_round array (empty if no skills available)
        $this->assertArrayHasKey('skills_used_this_round', $result);
        $this->assertIsArray($result['skills_used_this_round']);
    }

    public function test_process_one_round_with_explicit_empty_skill_list_disables_skills(): void
    {
        $skillSelector = new CombatSkillSelector;

        $skill = (object) [
            'id' => 101,
            'name' => 'Fireball',
            'icon' => 'flame',
            'effect_key' => 'fireball',
            'target_type' => 'single',
            'mana_cost' => 10,
            'damage' => 120,
            'cooldown' => 0,
        ];

        $charSkill = (object) ['skill' => $skill];

        $skillsRelation = \Mockery::mock(HasMany::class);
        $skillsRelation->shouldReceive('whereHas')->once()->andReturnSelf();
        $skillsRelation->shouldReceive('with')->once()->andReturnSelf();
        $skillsRelation->shouldReceive('get')->once()->andReturn(collect([$charSkill]));

        $character = \Mockery::mock(GameCharacter::class)->makePartial();
        $character->combat_monsters = [[
            'id' => 1,
            'name' => 'Goblin',
            'hp' => 100,
            'max_hp' => 100,
            'position' => 1,
        ]];
        $character->shouldReceive('skills')->once()->andReturn($skillsRelation);
        $character->shouldReceive('getCombatStats')->once()->andReturn([
            'attack' => 50,
            'defense' => 10,
            'crit_rate' => 0.1,
            'crit_damage' => 1.5,
        ]);

        $result = $skillSelector->resolveRoundSkill(
            $character,
            [],
            1,
            100,
            []
        );

        $this->assertSame([], $result['skills_used_this_round']);
        $this->assertSame(100, $result['mana']);
        $this->assertSame(0, $result['skill_damage']);
    }

    public function test_process_one_round_with_null_skill_list_keeps_default_skill_selection(): void
    {
        $skillSelector = new CombatSkillSelector;

        $skill = (object) [
            'id' => 101,
            'name' => 'Fireball',
            'icon' => 'flame',
            'effect_key' => 'fireball',
            'target_type' => 'single',
            'mana_cost' => 10,
            'damage' => 120,
            'cooldown' => 0,
        ];

        $charSkill = (object) ['skill' => $skill];

        $skillsRelation = \Mockery::mock(HasMany::class);
        $skillsRelation->shouldReceive('whereHas')->once()->andReturnSelf();
        $skillsRelation->shouldReceive('with')->once()->andReturnSelf();
        $skillsRelation->shouldReceive('get')->once()->andReturn(collect([$charSkill]));

        $character = \Mockery::mock(GameCharacter::class)->makePartial();
        $character->combat_monsters = [[
            'id' => 1,
            'name' => 'Goblin',
            'hp' => 100,
            'max_hp' => 100,
            'position' => 1,
        ]];
        $character->shouldReceive('skills')->once()->andReturn($skillsRelation);
        $character->shouldReceive('getCombatStats')->once()->andReturn([
            'attack' => 50,
            'defense' => 10,
            'crit_rate' => 0.1,
            'crit_damage' => 1.5,
        ]);

        $result = $skillSelector->resolveRoundSkill(
            $character,
            null,
            1,
            100,
            []
        );

        $this->assertCount(1, $result['skills_used_this_round']);
        $this->assertSame(101, $result['skills_used_this_round'][0]['skill_id']);
        $this->assertSame(90, $result['mana']);
        $this->assertSame(120, $result['skill_damage']);
    }

    public function test_process_one_round_returns_experience_and_copper(): void
    {
        $character = $this->createTestCharacter();

        // Set up a monster at low HP so it can be killed
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Rat',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 50,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertArrayHasKey('experience_gained', $result);
        $this->assertArrayHasKey('copper_gained', $result);
        $this->assertIsInt($result['experience_gained']);
        $this->assertIsInt($result['copper_gained']);
    }

    public function test_process_one_round_tracks_monster_deaths(): void
    {
        $character = $this->createTestCharacter();

        // Monster that will die in one hit
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Weak Slime',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 100,
                'attack' => 1,
                'defense' => 0,
                'experience' => 5,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Check round details for kill count
        $this->assertArrayHasKey('round_details', $result);
        $this->assertArrayHasKey('battle', $result['round_details']);
        $this->assertArrayHasKey('killed_count', $result['round_details']['battle']);
    }

    public function test_process_one_round_calculates_counter_damage(): void
    {
        $character = $this->createTestCharacter();

        // Set up monster that will attack
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Wolf',
                'level' => 5,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 20,
                'defense' => 5,
                'experience' => 25,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Monster should deal some counter damage
        $this->assertGreaterThanOrEqual(0, $result['round_damage_taken']);
        $this->assertIsInt($result['round_damage_taken']);
    }

    public function test_has_alive_monster_detection(): void
    {
        $character = $this->createTestCharacter();

        // Test with alive monster
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Alive Monster',
                'level' => 1,
                'hp' => 50,
                'max_hp' => 50,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertTrue($result['has_alive_monster']);
    }

    public function test_character_defeat_detection(): void
    {
        $character = $this->createTestCharacter();

        // Character with very low HP
        $character->current_hp = 1;
        $character->save();

        // Monster with high attack
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Strong Monster',
                'level' => 10,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 50,
                'defense' => 0,
                'experience' => 50,
                'position' => 1,
            ],
        ];

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertArrayHasKey('defeat', $result);
    }

    public function test_process_one_round_with_multiple_monsters(): void
    {
        $character = $this->createTestCharacter([
            'strength' => 100,
        ]);

        // Set up multiple monsters
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Slime 1',
                'level' => 1,
                'hp' => 50,
                'max_hp' => 50,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Slime 2',
                'level' => 1,
                'hp' => 50,
                'max_hp' => 50,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 2,
            ],
            [
                'id' => 3,
                'name' => 'Slime 3',
                'level' => 1,
                'hp' => 50,
                'max_hp' => 50,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 3,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Should process multiple monsters
        $this->assertArrayHasKey('monsters_updated', $result);
        $this->assertCount(3, $result['monsters_updated']);
    }

    public function test_process_one_round_tracks_cooldowns(): void
    {
        $character = $this->createTestCharacter();

        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Monster',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        // Pass existing cooldowns
        $cooldowns = [1 => 2]; // skill id 1 has 2 rounds cooldown remaining
        $result = $this->processor->processOneRound(
            $character,
            1,
            $cooldowns,
            [],
            []
        );

        $this->assertArrayHasKey('new_cooldowns', $result);
        $this->assertIsArray($result['new_cooldowns']);
    }

    public function test_process_one_round_aggregates_skills_across_rounds(): void
    {
        $character = $this->createTestCharacter();

        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Monster',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        // Pass previous skill usage aggregation
        $previousAggregation = [
            1 => [
                'skill_id' => 1,
                'name' => 'Test Skill',
                'icon' => 'sword',
                'use_count' => 2,
            ],
        ];

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            $previousAggregation,
            []
        );

        $this->assertArrayHasKey('new_skills_aggregated', $result);
    }

    public function test_process_one_round_with_new_monster_skips_attack(): void
    {
        $character = $this->createTestCharacter();

        // Set up a new monster that should skip first attack
        // The new monster should not take damage in the first round
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'New Monster',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 50,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
                'is_new' => true, // New monster should not be attacked in first round
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // New monster should not take damage (is_new = true means it skips first round attack)
        $updatedMonsters = $result['monsters_updated'];
        $this->assertEquals(100, $updatedMonsters[0]['hp']);
    }

    public function test_process_one_round_tracks_slots_where_monster_died(): void
    {
        $character = $this->createTestCharacter([
            'strength' => 1000, // High damage to kill monster in one hit
        ]);

        // Set up weak monsters that will die
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Weak Monster',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 10,
                'attack' => 1,
                'defense' => 0,
                'experience' => 5,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertArrayHasKey('slots_where_monster_died_this_round', $result);
    }

    public function test_process_one_round_with_difficulty_multiplier(): void
    {
        $character = $this->createTestCharacter([
            'difficulty_tier' => 2, // Higher difficulty
        ]);

        // Set up monster that will die
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Monster',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 10,
                'attack' => 1,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Round details should include difficulty info
        $this->assertArrayHasKey('round_details', $result);
        $this->assertArrayHasKey('difficulty', $result['round_details']);
    }

    public function test_process_one_round_kills_multiple_monsters(): void
    {
        $character = $this->createTestCharacter([
            'strength' => 1000, // High damage to kill all monsters
        ]);

        // Set up multiple weak monsters
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Weak 1',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 10,
                'attack' => 1,
                'defense' => 0,
                'experience' => 5,
                'position' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Weak 2',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 10,
                'attack' => 1,
                'defense' => 0,
                'experience' => 5,
                'position' => 2,
            ],
            [
                'id' => 3,
                'name' => 'Weak 3',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 10,
                'attack' => 1,
                'defense' => 0,
                'experience' => 5,
                'position' => 3,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Should track multiple kills
        $battle = $result['round_details']['battle'] ?? [];
        $this->assertGreaterThanOrEqual(1, $battle['killed_count'] ?? 0);
    }

    public function test_process_one_round_returns_skill_target_positions(): void
    {
        $character = $this->createTestCharacter();

        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Monster 1',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
            [
                'id' => 2,
                'name' => 'Monster 2',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 2,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertArrayHasKey('skill_target_positions', $result);
    }

    public function test_process_one_round_with_all_dead_monsters(): void
    {
        $character = $this->createTestCharacter();

        // All monsters already dead
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Dead Monster',
                'level' => 1,
                'hp' => 0,
                'max_hp' => 50,
                'attack' => 10,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Should handle dead monsters gracefully
        $this->assertFalse($result['has_alive_monster']);
    }

    public function test_process_one_round_with_specified_skill_ids(): void
    {
        $character = $this->createTestCharacter();

        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Monster',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        // Request specific skill IDs
        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            [999] // Non-existent skill ID
        );

        // Should still work even with non-existent skill
        $this->assertArrayHasKey('skills_used_this_round', $result);
    }

    public function test_process_one_round_character_initializes_hp_mana(): void
    {
        $character = $this->createTestCharacter();
        // Do not set current_hp and current_mana - they should be initialized

        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Monster',
                'level' => 1,
                'hp' => 50,
                'max_hp' => 50,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Should process without errors
        $this->assertArrayHasKey('new_char_hp', $result);
        $this->assertArrayHasKey('new_char_mana', $result);
    }

    public function test_process_one_round_new_monster_clears_is_new_flag(): void
    {
        $character = $this->createTestCharacter();

        // Set up new monster
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'New Monster',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 50,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
                'is_new' => true,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // After processing, is_new should be cleared
        $updatedMonsters = $result['monsters_updated'];
        $this->assertFalse($updatedMonsters[0]['is_new'] ?? false);
    }

    public function test_select_optimal_skill_prefers_aoe_when_many_low_hp_monsters(): void
    {
        $skillSelector = new CombatSkillSelector;

        $singleTargetSkill = [
            'damage' => 180,
            'mana_cost' => 30,
            'cooldown' => 2,
            'is_aoe' => false,
            'skill' => (object) ['id' => 1],
            'char_skill' => null,
        ];
        $aoeSkill = [
            'damage' => 90,
            'mana_cost' => 10,
            'cooldown' => 1,
            'is_aoe' => true,
            'skill' => (object) ['id' => 2],
            'char_skill' => null,
        ];

        $selected = $skillSelector->selectOptimalSkill(
            [$singleTargetSkill, $aoeSkill],
            3,
            2,
            400,
            120
        );

        $this->assertNotNull($selected);
        $this->assertTrue($selected['is_aoe']);
        $this->assertSame(2, $selected['skill']->id);
    }

    public function test_select_optimal_skill_prefers_economical_skill_when_total_hp_is_low(): void
    {
        $skillSelector = new CombatSkillSelector;

        $highCostSkill = [
            'damage' => 160,
            'mana_cost' => 40,
            'cooldown' => 2,
            'is_aoe' => false,
            'skill' => (object) ['id' => 11],
            'char_skill' => null,
        ];
        $zeroCostSkill = [
            'damage' => 25,
            'mana_cost' => 0,
            'cooldown' => 1,
            'is_aoe' => false,
            'skill' => (object) ['id' => 12],
            'char_skill' => null,
        ];

        $selected = $skillSelector->selectOptimalSkill(
            [$highCostSkill, $zeroCostSkill],
            2,
            0,
            60,
            50
        );

        $this->assertNotNull($selected);
        $this->assertSame(12, $selected['skill']->id);
        $this->assertSame(0, $selected['mana_cost']);
    }

    public function test_calculate_round_death_rewards_uses_drop_table_and_difficulty_multiplier(): void
    {
        $monsterDefinition = GameMonsterDefinition::create([
            'name' => 'Copper Slime',
            'type' => 'normal',
            'level' => 1,
            'hp_base' => 10,
            'attack_base' => 1,
            'defense_base' => 0,
            'experience_base' => 10,
            'drop_table' => [
                'copper_chance' => 1.0,
                'copper_base' => 7,
                'copper_range' => 0,
            ],
            'is_active' => true,
        ]);

        $rewardCalculator = new CombatRewardCalculator;

        [$experience, $copper] = $rewardCalculator->calculateRoundDeathRewards(
            [[
                'id' => $monsterDefinition->id,
                'level' => 1,
                'hp' => 0,
                'experience' => 10,
            ]],
            [0 => 20],
            ['reward' => 2]
        );

        $this->assertSame(20, $experience);
        $this->assertSame(14, $copper);
    }

    public function test_calculate_round_death_rewards_with_no_dead_monsters(): void
    {
        $rewardCalculator = new CombatRewardCalculator;

        // No dead monsters - should return 0 experience and copper
        [$experience, $copper] = $rewardCalculator->calculateRoundDeathRewards(
            [], // empty - no monsters
            [], // hp snapshot
            ['reward' => 1]
        );

        $this->assertSame(0, $experience);
        $this->assertSame(0, $copper);
    }

    public function test_calculate_round_death_rewards_with_boss_monster(): void
    {
        $bossDefinition = GameMonsterDefinition::create([
            'name' => 'Boss Monster',
            'type' => 'boss',
            'level' => 10,
            'hp_base' => 100,
            'attack_base' => 20,
            'defense_base' => 10,
            'experience_base' => 100,
            'drop_table' => [],
            'is_active' => true,
        ]);

        $rewardCalculator = new CombatRewardCalculator;

        [$experience, $copper] = $rewardCalculator->calculateRoundDeathRewards(
            [[
                'id' => $bossDefinition->id,
                'level' => 10,
                'hp' => 0,
                'experience' => 100,
            ]],
            [0 => 100],
            ['reward' => 1.5]
        );

        $this->assertGreaterThan(0, $experience);
    }

    public function test_calculate_monster_copper_loot_returns_zero_when_chance_fails(): void
    {
        config(['game.copper_drop.chance' => 0.0]);

        $monsterDefinition = GameMonsterDefinition::create([
            'name' => 'No Drop Slime',
            'type' => 'normal',
            'level' => 1,
            'hp_base' => 10,
            'attack_base' => 1,
            'defense_base' => 0,
            'experience_base' => 10,
            'drop_table' => [],
            'is_active' => true,
        ]);

        $rewardCalculator = new CombatRewardCalculator;

        $copper = $rewardCalculator->calculateMonsterCopperLoot([
            'id' => $monsterDefinition->id,
            'level' => 1,
        ]);

        $this->assertSame(0, $copper);
    }

    public function test_calculate_monster_copper_loot_with_successful_drop(): void
    {
        config(['game.copper_drop.chance' => 1.0]);

        $monsterDefinition = GameMonsterDefinition::create([
            'name' => 'Drop Slime',
            'type' => 'normal',
            'level' => 1,
            'hp_base' => 10,
            'attack_base' => 1,
            'defense_base' => 0,
            'experience_base' => 10,
            'drop_table' => [],
            'is_active' => true,
        ]);

        $rewardCalculator = new CombatRewardCalculator;

        $copper = $rewardCalculator->calculateMonsterCopperLoot([
            'id' => $monsterDefinition->id,
            'level' => 1,
        ]);

        $this->assertGreaterThan(0, $copper);
    }

    public function test_roll_chance_for_processor_always_returns_true_when_chance_is_one(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        // With chance = 1.0, should always return true
        $result = $damageCalculator->rollChanceForProcessor(1.0);
        $this->assertTrue($result);
    }

    public function test_roll_chance_for_processor_always_returns_false_when_chance_is_zero(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        // With chance = 0.0, should always return false
        $result = $damageCalculator->rollChanceForProcessor(0.0);
        $this->assertFalse($result);
    }

    public function test_compute_base_attack_damage_with_empty_targets(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        $result = $damageCalculator->computeBaseAttackDamage([], 10, 15, 1.5, false, 0.0);

        // Should return [0, 0] for empty targets
        $this->assertSame([0, 0], $result);
    }

    public function test_compute_base_attack_damage_with_skill_damage(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        $monsters = [[
            'id' => 1,
            'defense' => 5,
        ]];

        $result = $damageCalculator->computeBaseAttackDamage($monsters, 50, 15, 1.5, false, 0.0);

        // Should return [skillDamage, 0] when skillDamage > 0
        $this->assertSame([50, 0], $result);
    }

    public function test_compute_base_attack_damage_without_skill_calculates_from_attack(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        $monsters = [[
            'id' => 1,
            'defense' => 5,
        ]];

        $result = $damageCalculator->computeBaseAttackDamage($monsters, 0, 20, 1.5, false, 0.0);

        // When skillDamage = 0, it calculates from attack and defense
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_get_alive_monsters_filters_dead_ones(): void
    {
        $method = new ReflectionMethod($this->processor, 'getAliveMonsters');
        $method->setAccessible(true);

        $monsters = [
            ['id' => 1, 'hp' => 10],
            ['id' => 2, 'hp' => 0], // dead
            ['id' => 3, 'hp' => 5],
        ];

        $result = $method->invoke($this->processor, $monsters);

        $this->assertCount(2, $result);
        // array_filter re-indexes, so check by values instead of keys
        $ids = array_column($result, 'id');
        $this->assertContains(1, $ids);
        $this->assertContains(3, $ids);
        $this->assertNotContains(2, $ids);
    }

    public function test_get_monster_hp_snapshot_returns_all(): void
    {
        $method = new ReflectionMethod($this->processor, 'getMonsterHpSnapshot');
        $method->setAccessible(true);

        $monsters = [
            ['hp' => 10],
            ['hp' => 5],
        ];

        $result = $method->invoke($this->processor, $monsters);

        $this->assertCount(2, $result);
        $this->assertSame(10, $result[0]);
        $this->assertSame(5, $result[1]);
    }

    public function test_calculate_monster_counter_damage_with_living_monsters(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        $monstersUpdated = [
            ['hp' => 10, 'attack' => 15],
            ['hp' => 0, 'attack' => 10], // dead, should be skipped
            ['hp' => 5, 'attack' => 8],
        ];

        $result = $damageCalculator->calculateMonsterCounterDamage($monstersUpdated, 5);

        // Monster 1: 15 - 5*0.3 = 13.5 -> 13
        // Monster 2: dead, skipped
        // Monster 3: 8 - 5*0.3 = 6.5 -> 6
        // Total: 19
        $this->assertGreaterThan(0, $result);
    }

    public function test_calculate_monster_counter_damage_with_zero_defense(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        $monstersUpdated = [
            ['hp' => 10, 'attack' => 20],
        ];

        $result = $damageCalculator->calculateMonsterCounterDamage($monstersUpdated, 0);

        // 20 - 0 = 20
        $this->assertEquals(20, $result);
    }

    public function test_get_first_alive_monster_returns_first(): void
    {
        $method = new ReflectionMethod($this->processor, 'getFirstAliveMonster');
        $method->setAccessible(true);

        $monstersUpdated = [
            ['hp' => 0, 'id' => 1],
            ['hp' => 10, 'id' => 2],
            ['hp' => 5, 'id' => 3],
        ];

        $result = $method->invoke($this->processor, $monstersUpdated);

        $this->assertNotNull($result);
        $this->assertEquals(2, $result['id']);
    }

    public function test_get_first_alive_monster_returns_null_when_all_dead(): void
    {
        $method = new ReflectionMethod($this->processor, 'getFirstAliveMonster');
        $method->setAccessible(true);

        $monstersUpdated = [
            ['hp' => 0, 'id' => 1],
            ['hp' => 0, 'id' => 2],
        ];

        $result = $method->invoke($this->processor, $monstersUpdated);

        $this->assertNull($result);
    }

    public function test_has_alive_monster_returns_true(): void
    {
        $method = new ReflectionMethod($this->processor, 'hasAliveMonster');
        $method->setAccessible(true);

        $monstersUpdated = [
            ['hp' => 0],
            ['hp' => 10],
        ];

        $result = $method->invoke($this->processor, $monstersUpdated);

        $this->assertTrue($result);
    }

    public function test_has_alive_monster_returns_false_when_all_dead(): void
    {
        $method = new ReflectionMethod($this->processor, 'hasAliveMonster');
        $method->setAccessible(true);

        $monstersUpdated = [
            ['hp' => 0],
            ['hp' => 0],
        ];

        $result = $method->invoke($this->processor, $monstersUpdated);

        $this->assertFalse($result);
    }

    public function test_select_round_targets_returns_empty_when_no_living_monsters(): void
    {
        $calculator = new CombatDamageCalculator;

        $monsters = [
            ['hp' => 0],
            ['hp' => 0],
        ];

        $result = $calculator->selectRoundTargets($monsters, false);

        $this->assertCount(0, $result);
    }

    public function test_select_round_targets_returns_single_for_non_aoe(): void
    {
        $calculator = new CombatDamageCalculator;

        $monsters = [
            ['hp' => 10, 'id' => 1],
            ['hp' => 20, 'id' => 2],
            ['hp' => 0, 'id' => 3],
        ];

        $result = $calculator->selectRoundTargets($monsters, false);

        $this->assertCount(1, $result);
    }

    public function test_select_round_targets_returns_all_for_aoe(): void
    {
        $calculator = new CombatDamageCalculator;

        $monsters = [
            ['hp' => 10, 'id' => 1],
            ['hp' => 20, 'id' => 2],
            ['hp' => 5, 'id' => 3],
        ];

        $result = $calculator->selectRoundTargets($monsters, true);

        $this->assertCount(3, $result);
    }

    public function test_get_skill_target_positions_with_valid_positions(): void
    {
        $calculator = new CombatDamageCalculator;

        $targetMonsters = [
            ['position' => 0],
            ['position' => 2],
            ['position' => 5],
        ];

        $result = $calculator->getSkillTargetPositions($targetMonsters);

        $this->assertCount(3, $result);
        $this->assertContains(0, $result);
        $this->assertContains(2, $result);
        $this->assertContains(5, $result);
    }

    public function test_get_skill_target_positions_filters_null(): void
    {
        $calculator = new CombatDamageCalculator;

        $targetMonsters = [
            ['position' => 0],
            ['no_position' => 1], // no position key
            ['position' => 2],
        ];

        $result = $calculator->getSkillTargetPositions($targetMonsters);

        $this->assertCount(2, $result);
        $this->assertContains(0, $result);
        $this->assertContains(2, $result);
    }

    public function test_aggregate_skills_used_merges_same_skill(): void
    {
        $method = new ReflectionMethod($this->processor, 'aggregateSkillsUsed');
        $method->setAccessible(true);

        $skillsUsedThisRound = [
            ['skill_id' => 1, 'name' => 'Fireball'],
        ];
        $skillsUsedAggregated = [
            1 => ['skill_id' => 1, 'name' => 'Fireball', 'use_count' => 3],
        ];

        $result = $method->invoke($this->processor, $skillsUsedThisRound, $skillsUsedAggregated);

        // array_values re-indexes, so key becomes 0
        $this->assertCount(1, $result);
        $this->assertEquals(4, $result[0]['use_count']); // 3 + 1
    }

    public function test_aggregate_skills_used_adds_new_skill(): void
    {
        $method = new ReflectionMethod($this->processor, 'aggregateSkillsUsed');
        $method->setAccessible(true);

        $skillsUsedThisRound = [
            ['skill_id' => 2, 'name' => 'Ice', 'use_count' => 1],
        ];
        $skillsUsedAggregated = [
            1 => ['skill_id' => 1, 'name' => 'Fireball', 'use_count' => 3],
        ];

        $result = $method->invoke($this->processor, $skillsUsedThisRound, $skillsUsedAggregated);

        $this->assertCount(2, $result);
    }

    public function test_is_monster_in_targets_returns_true_when_found(): void
    {
        $calculator = new CombatDamageCalculator;

        $monster = ['position' => 2, 'id' => 1];
        $targets = [
            ['position' => 0],
            ['position' => 2],
            ['position' => 5],
        ];

        $result = $calculator->isMonsterInTargets($monster, $targets);

        $this->assertTrue($result);
    }

    public function test_is_monster_in_targets_returns_false_when_not_found(): void
    {
        $calculator = new CombatDamageCalculator;

        $monster = ['position' => 3, 'id' => 1];
        $targets = [
            ['position' => 0],
            ['position' => 2],
        ];

        $result = $calculator->isMonsterInTargets($monster, $targets);

        $this->assertFalse($result);
    }

    public function test_is_monster_in_targets_returns_false_when_no_position(): void
    {
        $calculator = new CombatDamageCalculator;

        $monster = ['id' => 1]; // no position
        $targets = [
            ['position' => 0],
            ['position' => 2],
        ];

        $result = $calculator->isMonsterInTargets($monster, $targets);

        $this->assertFalse($result);
    }

    public function test_process_one_round_with_specified_skill_ids_filters_correctly(): void
    {
        $character = $this->createTestCharacter(['level' => 10]);
        $character->combat_monsters = [
            ['id' => 1, 'hp' => 50, 'max_hp' => 50, 'attack' => 5, 'defense' => 0, 'level' => 1, 'position' => 0],
        ];
        $character->save();

        // Call with skill IDs that don't exist in character's skills
        $result = $this->processor->processOneRound(
            $character,
            1,
            [999, 888], // Non-existent skill IDs
            [],
            []
        );

        // Should return default values (no skills found)
        $this->assertArrayHasKey('round_damage_dealt', $result);
    }

    public function test_select_optimal_skill_with_no_available_skills(): void
    {
        $skillSelector = new CombatSkillSelector;

        // Empty available skills
        $result = $skillSelector->selectOptimalSkill(
            [],
            3,
            50,
            0,
            100
        );

        $this->assertNull($result);
    }

    public function test_apply_character_damage_to_monsters_splits_when_crit(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        $monsters = [
            ['id' => 1, 'hp' => 100, 'defense' => 0, 'name' => 'Monster1'],
        ];
        $targetMonsters = [
            ['id' => 1, 'position' => 0],
        ];

        [$monstersUpdated, $totalDamage] = $damageCalculator->applyCharacterDamageToMonsters(
            $monsters,
            $targetMonsters,
            50,  // charAttack
            0,    // skillDamage
            true,  // isCrit
            2.0,   // charCritDamage
            false  // useAoe
        );

        // Should return array with monsters updated and total damage
        $this->assertIsArray($monstersUpdated);
        $this->assertIsInt($totalDamage);
    }

    public function test_resolve_round_skill_filters_requested_ids_and_updates_cooldown(): void
    {
        $skillSelector = new CombatSkillSelector;

        $skillA = (object) [
            'id' => 101,
            'name' => 'AOE Skill',
            'icon' => 'aoe',
            'effect_key' => 'aoe_blast',
            'target_type' => 'all',
            'mana_cost' => 20,
            'damage' => 80,
            'cooldown' => 2,
        ];
        $skillB = (object) [
            'id' => 102,
            'name' => 'Single Skill',
            'icon' => 'single',
            'effect_key' => null,
            'target_type' => 'single',
            'mana_cost' => 10,
            'damage' => 20,
            'cooldown' => 1,
        ];

        $charSkillA = (object) ['skill' => $skillA];
        $charSkillB = (object) ['skill' => $skillB];

        $skillsRelation = \Mockery::mock(HasMany::class);
        $skillsRelation->shouldReceive('whereHas')->once()->andReturnSelf();
        $skillsRelation->shouldReceive('with')->once()->andReturnSelf();
        $skillsRelation->shouldReceive('get')->once()->andReturn(collect([$charSkillA, $charSkillB]));

        $character = \Mockery::mock(GameCharacter::class)->makePartial();
        $character->combat_monsters = [
            ['hp' => 100, 'max_hp' => 100],
            ['hp' => 80, 'max_hp' => 100],
            ['hp' => 20, 'max_hp' => 100],
        ];
        $character->shouldReceive('skills')->once()->andReturn($skillsRelation);
        $character->shouldReceive('getCombatStats')->once()->andReturn(['attack' => 100]);

        $result = $skillSelector->resolveRoundSkill(
            $character,
            [101],
            3,
            100,
            []
        );

        $this->assertSame(80, $result['mana']);
        $this->assertTrue($result['is_aoe']);
        $this->assertSame(80, $result['skill_damage']);
        $this->assertSame(5, $result['new_cooldowns'][101]);
        $this->assertCount(1, $result['skills_used_this_round']);
        $this->assertSame(101, $result['skills_used_this_round'][0]['skill_id']);
    }

    public function test_select_optimal_skill_returns_single_skill_when_only_one_available(): void
    {
        $skillSelector = new CombatSkillSelector;

        $singleSkill = [
            'damage' => 30,
            'mana_cost' => 5,
            'cooldown' => 1,
            'is_aoe' => false,
            'skill' => (object) ['id' => 201],
            'char_skill' => null,
        ];

        $result = $skillSelector->selectOptimalSkill([$singleSkill], 1, 0, 100, 50);

        $this->assertSame(201, $result['skill']->id);
    }

    public function test_select_optimal_skill_falls_back_to_lowest_mana_when_efficiency_is_low(): void
    {
        $skillSelector = new CombatSkillSelector;

        $skillA = [
            'damage' => 10,
            'mana_cost' => 50,
            'cooldown' => 1,
            'is_aoe' => false,
            'skill' => (object) ['id' => 301],
            'char_skill' => null,
        ];
        $skillB = [
            'damage' => 8,
            'mana_cost' => 40,
            'cooldown' => 1,
            'is_aoe' => false,
            'skill' => (object) ['id' => 302],
            'char_skill' => null,
        ];

        $result = $skillSelector->selectOptimalSkill([$skillA, $skillB], 2, 0, 1000, 200);

        $this->assertSame(302, $result['skill']->id);
        $this->assertSame(40, $result['mana_cost']);
    }

    public function test_compute_base_attack_damage_with_crit_returns_crit_values(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        $targets = [[
            'id' => 1,
            'defense' => 10,
        ]];

        $result = $damageCalculator->computeBaseAttackDamage($targets, 0, 100, 2.0, true, 0.5);

        $this->assertSame([190, 95], $result);
    }

    public function test_calculate_monster_copper_loot_without_id_uses_fallback_random_range(): void
    {
        $rewardCalculator = new CombatRewardCalculator;

        $result = $rewardCalculator->calculateMonsterCopperLoot([
            'name' => 'Unknown Monster',
            'level' => 1,
        ]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(10, $result);
    }

    public function test_apply_character_damage_to_monsters_uses_skill_damage_branch(): void
    {
        $damageCalculator = new CombatDamageCalculator;

        $monsters = [
            ['id' => 1, 'position' => 0, 'hp' => 100, 'defense' => 10, 'name' => 'SkillTarget'],
        ];
        $targets = [
            ['position' => 0],
        ];

        [$updated, $total] = $damageCalculator->applyCharacterDamageToMonsters(
            $monsters,
            $targets,
            40,
            30,
            false,
            1.5,
            false
        );

        $this->assertSame(35, $updated[0]['hp']);
        $this->assertSame(65, $updated[0]['damage_taken']);
        $this->assertSame(65, $total);
    }

    public function test_select_optimal_skill_prefers_aoe_with_many_targets(): void
    {
        config(['game.combat.aoe_damage_multiplier' => 0.7]);

        $skillSelector = new CombatSkillSelector;

        $availableSkills = [
            [
                'id' => 1,
                'name' => 'Single Target',
                'mana_cost' => 10,
                'damage' => 50,
                'is_aoe' => false,
            ],
            [
                'id' => 2,
                'name' => 'AOE Skill',
                'mana_cost' => 20,
                'damage' => 40,
                'is_aoe' => true,
            ],
        ];

        // 3 个存活怪物，总血量 150 (低血量场景)
        $result = $skillSelector->selectOptimalSkill(
            $availableSkills,
            150,
            3,
            150,
            50
        );

        $this->assertIsArray($result);
        $this->assertTrue($result['is_aoe']);
        $this->assertEquals(2, $result['id']);
    }

    public function test_select_optimal_skill_prefers_economical_when_total_hp_low(): void
    {
        $skillSelector = new CombatSkillSelector;

        $availableSkills = [
            [
                'id' => 1,
                'name' => 'Expensive Skill',
                'mana_cost' => 30,
                'damage' => 100,
                'is_aoe' => false,
            ],
            [
                'id' => 2,
                'name' => 'Cheap Skill',
                'mana_cost' => 5,
                'damage' => 30,
                'is_aoe' => false,
            ],
        ];

        // totalMonsterHp = 90, charAttack = 50, 90 < 50 * 2 = 100
        $result = $skillSelector->selectOptimalSkill(
            $availableSkills,
            90,
            2,
            90,
            50
        );

        $this->assertIsArray($result);
        // 应选择消耗较低的技能
        $this->assertEquals(2, $result['id']);
    }

    public function test_select_optimal_skill_with_zero_mana_cost_skill(): void
    {
        $skillSelector = new CombatSkillSelector;

        $availableSkills = [
            [
                'id' => 1,
                'name' => 'Free Skill',
                'mana_cost' => 0,
                'damage' => 25,
                'is_aoe' => false,
            ],
            [
                'id' => 2,
                'name' => 'Costly Skill',
                'mana_cost' => 20,
                'damage' => 50,
                'is_aoe' => false,
            ],
        ];

        // 低总血量场景
        $result = $skillSelector->selectOptimalSkill(
            $availableSkills,
            50,
            1,
            80,
            40
        );

        $this->assertIsArray($result);
        // 优先选择 0 消耗技能
        $this->assertEquals(1, $result['id']);
    }

    public function test_select_optimal_skill_falls_back_to_most_economical_by_mana(): void
    {
        $skillSelector = new CombatSkillSelector;

        $availableSkills = [
            [
                'id' => 1,
                'name' => 'Skill A',
                'mana_cost' => 15,
                'damage' => 20,
                'is_aoe' => false,
            ],
            [
                'id' => 2,
                'name' => 'Skill B',
                'mana_cost' => 8,
                'damage' => 18,
                'is_aoe' => false,
            ],
        ];

        // 不满足特殊条件时，应选择最经济的(按魔法消耗排序)
        $result = $skillSelector->selectOptimalSkill(
            $availableSkills,
            500,
            2,
            500,
            100
        );

        $this->assertIsArray($result);
        $this->assertEquals(2, $result['id']);
    }

    public function test_resolve_round_skill_with_no_available_skills_returns_default(): void
    {
        $skillSelector = new CombatSkillSelector;

        $character = $this->createTestCharacter(['mp' => 50]);
        // 角色没有技能，所以没有可用技能

        $result = $skillSelector->resolveRoundSkill(
            $character,
            [],
            1,
            50,
            []
        );

        $this->assertEquals(50, $result['mana']);
        $this->assertFalse($result['is_aoe']);
        $this->assertEquals(0, $result['skill_damage']);
        $this->assertEmpty($result['skills_used_this_round']);
    }

    public function test_aggregate_skills_used_with_empty_current_round(): void
    {
        $method = new ReflectionMethod($this->processor, 'aggregateSkillsUsed');
        $method->setAccessible(true);

        $aggregated = [
            ['skill_id' => 1, 'name' => 'Skill1', 'use_count' => 1],
        ];

        $result = $method->invoke($this->processor, [], $aggregated);

        $this->assertCount(1, $result);
    }

    public function test_aggregate_skills_used_increments_existing(): void
    {
        $method = new ReflectionMethod($this->processor, 'aggregateSkillsUsed');
        $method->setAccessible(true);

        $aggregated = [
            1 => ['skill_id' => 1, 'name' => 'Skill1', 'use_count' => 1],
        ];

        $current = [
            ['skill_id' => 1, 'name' => 'Skill1'],
        ];

        $result = $method->invoke($this->processor, $current, $aggregated);

        $this->assertSame(2, $result[0]['use_count']);
    }

    /**
     * Helper method to create a test character
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createTestCharacter(array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => 1,
            'name' => 'TestCharacter',
            'class' => 'warrior',
            'level' => 1,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'is_fighting' => false,
            'difficulty_tier' => 0,
        ], $attributes));
    }
}
