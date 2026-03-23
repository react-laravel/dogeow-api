<?php

namespace Tests\Unit\Policies;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\User;
use App\Policies\Game\GameItemPolicy;
use Tests\TestCase;

class GameItemPolicyTest extends TestCase
{
    private GameItemPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new GameItemPolicy;
    }

    private function createUser(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    private function createCharacter(int $userId): GameCharacter
    {
        return (new GameCharacter)->forceFill([
            'user_id' => $userId,
        ]);
    }

    private function createItem(int $characterUserId): GameItem
    {
        $item = new GameItem;
        $item->setRelation('character', $this->createCharacter($characterUserId));

        return $item;
    }

    public function test_view_any_returns_true_for_character_owner(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(1);

        $this->assertTrue($this->policy->viewAny($user, $character));
    }

    public function test_view_any_returns_false_for_non_owner_non_admin(): void
    {
        $user = $this->createUser(1);
        $character = $this->createCharacter(999);

        $this->assertFalse($this->policy->viewAny($user, $character));
    }

    public function test_view_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->view($user, $item));
    }

    public function test_view_returns_false_for_non_owner_non_admin(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->view($user, $item));
    }

    public function test_use_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->use($user, $item));
    }

    public function test_use_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->use($user, $item));
    }

    public function test_equip_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->equip($user, $item));
    }

    public function test_equip_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->equip($user, $item));
    }

    public function test_unequip_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->unequip($user, $item));
    }

    public function test_unequip_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->unequip($user, $item));
    }

    public function test_drop_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->drop($user, $item));
    }

    public function test_drop_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->drop($user, $item));
    }

    public function test_sell_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->sell($user, $item));
    }

    public function test_sell_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->sell($user, $item));
    }

    public function test_trade_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->trade($user, $item));
    }

    public function test_trade_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->trade($user, $item));
    }

    public function test_store_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->store($user, $item));
    }

    public function test_store_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->store($user, $item));
    }

    public function test_retrieve_returns_true_for_item_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->retrieve($user, $item));
    }

    public function test_retrieve_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->retrieve($user, $item));
    }
}
