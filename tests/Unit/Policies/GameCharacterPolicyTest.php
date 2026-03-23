<?php

namespace Tests\Unit\Policies;

use App\Models\Game\GameCharacter;
use App\Models\User;
use App\Policies\Game\GameCharacterPolicy;
use Tests\TestCase;

class GameCharacterPolicyTest extends TestCase
{
    private GameCharacterPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new GameCharacterPolicy;
    }

    private function createUser(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    private function createCharacter(int $userId): GameCharacter
    {
        $character = new GameCharacter;
        $character->user_id = $userId;

        return $character;
    }

    public function test_view_any_returns_true(): void
    {
        $user = $this->createUser(1);
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_create_returns_true(): void
    {
        $user = $this->createUser(1);
        $this->assertTrue($this->policy->create($user));
    }

    public function test_view_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(1);

        $this->assertTrue($this->policy->view($user, $character));
    }

    public function test_view_returns_false_for_non_owner_non_admin(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(999);

        $this->assertFalse($this->policy->view($user, $character));
    }

    public function test_update_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(1);

        $this->assertTrue($this->policy->update($user, $character));
    }

    public function test_update_returns_false_for_non_owner_non_admin(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(999);

        $this->assertFalse($this->policy->update($user, $character));
    }

    public function test_delete_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(1);

        $this->assertTrue($this->policy->delete($user, $character));
    }

    public function test_delete_returns_false_for_non_owner_non_admin(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(999);

        $this->assertFalse($this->policy->delete($user, $character));
    }

    public function test_combat_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(1);

        $this->assertTrue($this->policy->combat($user, $character));
    }

    public function test_combat_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(999);

        $this->assertFalse($this->policy->combat($user, $character));
    }

    public function test_use_skill_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(1);

        $this->assertTrue($this->policy->useSkill($user, $character));
    }

    public function test_use_skill_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(999);

        $this->assertFalse($this->policy->useSkill($user, $character));
    }

    public function test_manage_inventory_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(1);

        $this->assertTrue($this->policy->manageInventory($user, $character));
    }

    public function test_manage_inventory_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(999);

        $this->assertFalse($this->policy->manageInventory($user, $character));
    }

    public function test_view_combat_logs_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(1);

        $this->assertTrue($this->policy->viewCombatLogs($user, $character));
    }

    public function test_view_combat_logs_returns_false_for_non_owner_non_admin(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(999);

        $this->assertFalse($this->policy->viewCombatLogs($user, $character));
    }
}
