<?php

namespace Tests\Unit\Controllers\Concerns;

use App\Http\Controllers\Concerns\GetCurrentUserId;
use App\Models\User;
use Tests\TestCase;

class GetCurrentUserIdTest extends TestCase
{
    public function test_get_current_user_id_returns_default_for_guest(): void
    {
        $controller = new class
        {
            use GetCurrentUserId;

            public function currentUserId(): int
            {
                return $this->getCurrentUserId();
            }

            public function authenticated(): bool
            {
                return $this->isAuthenticated();
            }
        };

        $this->assertSame(1, $controller->currentUserId());
        $this->assertFalse($controller->authenticated());
    }

    public function test_get_current_user_id_returns_authenticated_user_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $controller = new class
        {
            use GetCurrentUserId;

            public function currentUserId(): int
            {
                return $this->getCurrentUserId();
            }

            public function authenticated(): bool
            {
                return $this->isAuthenticated();
            }
        };

        $this->assertSame($user->id, $controller->currentUserId());
        $this->assertTrue($controller->authenticated());
    }
}
