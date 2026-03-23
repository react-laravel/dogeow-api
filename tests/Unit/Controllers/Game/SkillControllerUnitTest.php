<?php

namespace Tests\Unit\Controllers\Game;

use App\Http\Controllers\Api\Game\SkillController;
use App\Http\Requests\Game\LearnSkillRequest;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameCharacterSkill;
use App\Models\Game\GameSkillDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SkillControllerUnitTest extends TestCase
{
    use RefreshDatabase;

    private SkillController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SkillController;
    }

    public function test_index_returns_active_skills_with_learned_state(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['class' => 'warrior', 'skill_points' => 3]);
        $sharedSkill = $this->createSkillDefinition(['name' => 'Slash', 'class_restriction' => 'all']);
        $classSkill = $this->createSkillDefinition(['name' => 'Guard', 'class_restriction' => 'warrior']);
        $this->createSkillDefinition(['name' => 'Fireball', 'class_restriction' => 'mage']);
        $this->createSkillDefinition(['name' => 'Disabled', 'class_restriction' => 'all', 'is_active' => false]);

        GameCharacterSkill::create([
            'character_id' => $character->id,
            'skill_id' => $classSkill->id,
        ]);

        $request = Request::create('/api/rpg/skills', 'GET', ['character_id' => $character->id]);
        $request->setUserResolver(fn () => $user);

        $response = $this->controller->index($request);
        $payload = json_decode($response->getContent(), true);
        $data = $payload['data'];
        $skillsById = collect($data['skills'])->keyBy('id');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, $data['skill_points']);
        $this->assertTrue($skillsById->has($sharedSkill->id));
        $this->assertTrue($skillsById->has($classSkill->id));
        $this->assertFalse($skillsById->has($this->getSkillIdByName($data['skills'], 'Fireball')));
        $this->assertFalse($skillsById->has($this->getSkillIdByName($data['skills'], 'Disabled')));
        $this->assertFalse($skillsById[$sharedSkill->id]['is_learned']);
        $this->assertTrue($skillsById[$classSkill->id]['is_learned']);
        $this->assertSame(1, $skillsById[$classSkill->id]['level']);
    }

    public function test_learn_returns_error_when_skill_points_are_insufficient(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['skill_points' => 0]);
        $skill = $this->createSkillDefinition(['skill_points_cost' => 2]);

        $response = $this->controller->learn($this->makeLearnRequest($user, $character, [
            'skill_id' => $skill->id,
        ]));

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('技能点不足，学习该技能需要 2 点', $payload['message']);
    }

    public function test_learn_rejects_skill_with_wrong_class(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['class' => 'warrior', 'skill_points' => 3]);
        $skill = $this->createSkillDefinition([
            'class_restriction' => 'mage',
            'skill_points_cost' => 1,
        ]);

        $response = $this->controller->learn($this->makeLearnRequest($user, $character, [
            'skill_id' => $skill->id,
        ]));

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('该技能不适合你的职业', $payload['message']);
    }

    public function test_learn_rejects_already_learned_skill(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['skill_points' => 3]);
        $skill = $this->createSkillDefinition();

        GameCharacterSkill::create([
            'character_id' => $character->id,
            'skill_id' => $skill->id,
        ]);

        $response = $this->controller->learn($this->makeLearnRequest($user, $character, [
            'skill_id' => $skill->id,
        ]));

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('已经学习了该技能', $payload['message']);
    }

    public function test_learn_rejects_missing_effect_key_prerequisite(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['class' => 'warrior', 'skill_points' => 3]);
        $requiredSkill = $this->createSkillDefinition([
            'name' => 'Power Strike',
            'effect_key' => 'power_strike',
            'class_restriction' => 'warrior',
        ]);
        $skill = $this->createSkillDefinition([
            'name' => 'Whirlwind',
            'effect_key' => 'whirlwind',
            'prerequisite_effect_key' => 'power_strike',
            'class_restriction' => 'warrior',
        ]);

        $response = $this->controller->learn($this->makeLearnRequest($user, $character, [
            'skill_id' => $skill->id,
        ]));

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('需要先学习前置技能: ' . $requiredSkill->name, $payload['message']);
    }

    public function test_learn_rejects_missing_skill_id_prerequisite(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['skill_points' => 3]);
        $requiredSkill = $this->createSkillDefinition(['name' => 'Shield Bash']);
        $skill = $this->createSkillDefinition([
            'name' => 'Shield Wall',
            'prerequisite_effect_key' => null,
            'prerequisite_skill_id' => $requiredSkill->id,
        ]);

        $response = $this->controller->learn($this->makeLearnRequest($user, $character, [
            'skill_id' => $skill->id,
        ]));

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('需要先学习前置技能: ' . $requiredSkill->name, $payload['message']);
    }

    public function test_learn_persists_skill_and_decrements_points_on_success(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user, ['skill_points' => 4]);
        $prerequisite = $this->createSkillDefinition([
            'name' => 'Focus',
            'effect_key' => 'focus',
        ]);
        GameCharacterSkill::create([
            'character_id' => $character->id,
            'skill_id' => $prerequisite->id,
        ]);
        $skill = $this->createSkillDefinition([
            'name' => 'Focused Slash',
            'effect_key' => 'focused_slash',
            'prerequisite_effect_key' => 'focus',
            'skill_points_cost' => 2,
        ]);

        $response = $this->controller->learn($this->makeLearnRequest($user, $character, [
            'skill_id' => $skill->id,
        ]));

        $payload = json_decode($response->getContent(), true);
        $character->refresh();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('技能学习成功', $payload['message']);
        $this->assertSame(2, $payload['data']['skill_points']);
        $this->assertSame(2, $character->skill_points);
        $this->assertDatabaseHas('game_character_skills', [
            'character_id' => $character->id,
            'skill_id' => $skill->id,
        ]);
    }

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Hero ' . $user->id,
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 10,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
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
        static $counter = 1;

        $extraAttributes = [];
        if (array_key_exists('prerequisite_effect_key', $attributes)) {
            $extraAttributes['prerequisite_effect_key'] = $attributes['prerequisite_effect_key'];
            unset($attributes['prerequisite_effect_key']);
        }

        $skill = GameSkillDefinition::create(array_merge([
            'name' => 'Skill ' . $counter,
            'description' => 'Test skill ' . $counter,
            'type' => 'active',
            'class_restriction' => 'all',
            'mana_cost' => 0,
            'cooldown' => 0,
            'effect_key' => 'skill_' . $counter,
            'target_type' => 'single',
            'is_active' => true,
            'skill_points_cost' => 1,
            'base_damage' => 10,
        ], $attributes));

        if ($extraAttributes !== []) {
            $skill->forceFill($extraAttributes)->save();
        }

        $counter++;

        return $skill;
    }

    /**
     * @param  array<int, array<string, mixed>>  $skills
     */
    private function getSkillIdByName(array $skills, string $name): ?int
    {
        foreach ($skills as $skill) {
            if (($skill['name'] ?? null) === $name) {
                return (int) $skill['id'];
            }
        }

        return null;
    }

    private function makeLearnRequest(User $user, GameCharacter $character, array $payload = []): LearnSkillRequest
    {
        $request = LearnSkillRequest::create('/api/rpg/skills/learn', 'POST', array_merge([
            'character_id' => $character->id,
        ], $payload));
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
