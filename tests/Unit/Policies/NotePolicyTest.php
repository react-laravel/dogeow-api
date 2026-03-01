<?php

namespace Tests\Unit\Policies;

use App\Models\Note\Note;
use App\Models\User;
use App\Policies\Note\NotePolicy;
use Tests\TestCase;

class NotePolicyTest extends TestCase
{
    private NotePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new NotePolicy;
    }

    private function createUser(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    private function createNote(int $userId, bool $isPublic = false): Note
    {
        $note = new Note;
        $note->user_id = $userId;
        $note->is_public = $isPublic;

        return $note;
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

    public function test_view_returns_true_for_public_note(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(999, true);

        $this->assertTrue($this->policy->view($user, $note));
    }

    public function test_view_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(1, false);

        $this->assertTrue($this->policy->view($user, $note));
    }

    public function test_view_returns_false_for_non_owner_private_note(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(999, false);

        $this->assertFalse($this->policy->view($user, $note));
    }

    public function test_update_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(1);

        $this->assertTrue($this->policy->update($user, $note));
    }

    public function test_update_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(999);

        $this->assertFalse($this->policy->update($user, $note));
    }

    public function test_delete_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(1);

        $this->assertTrue($this->policy->delete($user, $note));
    }

    public function test_delete_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(999);

        $this->assertFalse($this->policy->delete($user, $note));
    }

    public function test_share_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(1);

        $this->assertTrue($this->policy->share($user, $note));
    }

    public function test_share_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(999);

        $this->assertFalse($this->policy->share($user, $note));
    }

    public function test_publish_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(1);

        $this->assertTrue($this->policy->publish($user, $note));
    }

    public function test_publish_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $note = $this->createNote(999);

        $this->assertFalse($this->policy->publish($user, $note));
    }
}
