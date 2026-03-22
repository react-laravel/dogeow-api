<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\ProfileController;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    protected ProfileController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProfileController;
    }

    public function test_edit_returns_user_profile(): void
    {
        // TODO: Implement test
    }

    public function test_edit_returns_correct_fields(): void
    {
        // TODO: Implement test
    }

    public function test_update_validates_request(): void
    {
        // TODO: Implement test
    }

    public function test_update_changes_email(): void
    {
        // TODO: Implement test
    }

    public function test_update_clears_email_verified_at_when_email_changes(): void
    {
        // TODO: Implement test
    }

    public function test_update_preserves_email_verified_at_when_email_unchanged(): void
    {
        // TODO: Implement test
    }

    public function test_destroy_requires_password(): void
    {
        // TODO: Implement test
    }

    public function test_destroy_validates_current_password(): void
    {
        // TODO: Implement test
    }

    public function test_destroy_deletes_user_items(): void
    {
        // TODO: Implement test
    }

    public function test_destroy_deletes_user(): void
    {
        // TODO: Implement test
    }

    public function test_destroy_runs_in_transaction(): void
    {
        // TODO: Implement test
    }
}
