<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Tests\TestCase;

class BaseControllerTest extends TestCase
{
    public function test_success_error_fail_and_current_user_id_helpers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $controller = new class extends Controller
        {
            public function successResponse(mixed $data = null, string $message = 'Success', int $code = 200)
            {
                return $this->success($data, $message, $code);
            }

            public function errorResponse(string $message, mixed $data = null, int $code = 422)
            {
                return $this->error($message, $data, $code);
            }

            public function failResponse(string $message, mixed $errors = null, int $code = 422)
            {
                return $this->fail($message, $errors, $code);
            }

            public function currentUserId(): int
            {
                return $this->getCurrentUserId();
            }
        };

        $success = json_decode($controller->successResponse(['ok' => true], 'Done', 201)->getContent(), true);
        $error = json_decode($controller->errorResponse('Nope', ['bad'], 409)->getContent(), true);
        $fail = json_decode($controller->failResponse('Fail', ['x'], 418)->getContent(), true);

        $this->assertSame('Done', $success['message']);
        $this->assertSame(['ok' => true], $success['data']);
        $this->assertSame('Nope', $error['message']);
        $this->assertSame(['bad'], $error['errors']);
        $this->assertSame('Fail', $fail['message']);
        $this->assertSame(['x'], $fail['errors']);
        $this->assertSame($user->id, $controller->currentUserId());
    }
}
