<?php

namespace Tests\Unit\Policies;

use App\Models\Thing\Item;
use App\Models\User;
use App\Policies\Thing\ThingItemPolicy;
use Tests\TestCase;

class ThingItemPolicyTest extends TestCase
{
    private ThingItemPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ThingItemPolicy;
    }

    private function createUser(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    private function createItem(int $userId, bool $isPublic = false): Item
    {
        $item = new Item;
        $item->user_id = $userId;
        $item->is_public = $isPublic;

        return $item;
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

    public function test_view_returns_true_for_public_item(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999, true);

        $this->assertTrue($this->policy->view($user, $item));
    }

    public function test_view_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1, false);

        $this->assertTrue($this->policy->view($user, $item));
    }

    public function test_view_returns_false_for_non_owner_private_item(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999, false);

        $this->assertFalse($this->policy->view($user, $item));
    }

    public function test_update_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->update($user, $item));
    }

    public function test_update_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->update($user, $item));
    }

    public function test_delete_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->delete($user, $item));
    }

    public function test_delete_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->delete($user, $item));
    }

    public function test_share_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->share($user, $item));
    }

    public function test_share_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->share($user, $item));
    }

    public function test_archive_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(1);

        $this->assertTrue($this->policy->archive($user, $item));
    }

    public function test_archive_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $item = $this->createItem(999);

        $this->assertFalse($this->policy->archive($user, $item));
    }
}
