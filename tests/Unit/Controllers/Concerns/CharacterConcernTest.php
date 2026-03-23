<?php

namespace Tests\Unit\Controllers\Concerns;

use App\Http\Controllers\Concerns\CharacterConcern;
use App\Models\Game\GameCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterConcernTest extends TestCase
{
    use CharacterConcern;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function get_character_returns_first_character_for_user_when_no_character_id_provided(): void
    {
        // Arrange - create multiple characters for user
        $firstCharacter = GameCharacter::create([
            'user_id' => $this->user->id,
            'name' => 'First Character',
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 1,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 100,
            'current_mana' => 50,
        ]);
        $secondCharacter = GameCharacter::create([
            'user_id' => $this->user->id,
            'name' => 'Second Character',
            'class' => 'mage',
            'gender' => 'female',
            'level' => 5,
            'experience' => 0,
            'copper' => 200,
            'strength' => 8,
            'dexterity' => 12,
            'vitality' => 9,
            'energy' => 15,
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 80,
            'current_mana' => 100,
        ]);

        // Create request without character_id
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $this->user);

        // Act
        $result = $this->getCharacter($request);

        // Assert - should return the first character (lowest id)
        $this->assertEquals($firstCharacter->id, $result->id);
        $this->assertEquals('First Character', $result->name);
    }

    #[Test]
    public function get_character_returns_specific_character_when_character_id_provided(): void
    {
        // Arrange
        $character1 = GameCharacter::create([
            'user_id' => $this->user->id,
            'name' => 'Character One',
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 1,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 100,
            'current_mana' => 50,
        ]);
        $character2 = GameCharacter::create([
            'user_id' => $this->user->id,
            'name' => 'Character Two',
            'class' => 'mage',
            'gender' => 'female',
            'level' => 10,
            'experience' => 0,
            'copper' => 500,
            'strength' => 8,
            'dexterity' => 12,
            'vitality' => 9,
            'energy' => 15,
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 80,
            'current_mana' => 100,
        ]);

        // Create request with specific character_id (via query)
        $request = Request::create('/test', 'GET', ['character_id' => (string) $character2->id]);
        $request->setUserResolver(fn () => $this->user);

        // Act
        $result = $this->getCharacter($request);

        // Assert
        $this->assertEquals($character2->id, $result->id);
        $this->assertEquals('Character Two', $result->name);
    }

    #[Test]
    public function get_character_throws_exception_when_character_not_found(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET', ['character_id' => '99999']);
        $request->setUserResolver(fn () => $this->user);

        // Act & Assert
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->getCharacter($request);
    }

    #[Test]
    public function get_character_returns_character_owned_by_requesting_user_only(): void
    {
        // Arrange - create another user's character
        $otherUser = User::factory()->create();
        $otherUserCharacter = GameCharacter::create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Character',
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 20,
            'experience' => 0,
            'copper' => 1000,
            'strength' => 50,
            'dexterity' => 30,
            'vitality' => 40,
            'energy' => 20,
            'skill_points' => 0,
            'stat_points' => 0,
            'difficulty_tier' => 0,
            'is_fighting' => false,
            'current_hp' => 500,
            'current_mana' => 200,
        ]);

        $request = Request::create('/test', 'GET', ['character_id' => (string) $otherUserCharacter->id]);
        $request->setUserResolver(fn () => $this->user);

        // Act & Assert - should throw because other user's character should not be accessible
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->getCharacter($request);
    }

    #[Test]
    public function get_character_id_returns_null_when_no_character_id_in_request(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');

        // Act
        $result = $this->getCharacterId($request);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function get_character_id_returns_integer_from_query_param(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET', ['character_id' => '42']);

        // Act
        $result = $this->getCharacterId($request);

        // Assert
        $this->assertEquals(42, $result);
    }

    #[Test]
    public function get_character_id_returns_integer_from_input_param(): void
    {
        // Arrange
        $request = Request::create('/test', 'POST', ['character_id' => '99']);

        // Act
        $result = $this->getCharacterId($request);

        // Assert
        $this->assertEquals(99, $result);
    }
}
