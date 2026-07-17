<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SsoTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class SsoController extends Controller
{
    public function issue(Request $request, SsoTicketService $tickets): JsonResponse
    {
        $validated = $request->validate([
            'client' => ['required', 'string', Rule::in(array_keys((array) config('sso.clients', [])))],
            'return_to' => ['required', 'url:http,https', 'max:2048'],
            'code_challenge' => ['nullable', 'string', 'size:43', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        try {
            $result = $tickets->issue(
                $validated['client'],
                $validated['return_to'],
                $request->user(),
                $validated['code_challenge'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), null, 422);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), null, 403);
        }

        return $this->success($result, 'SSO ticket issued');
    }

    public function exchange(Request $request, SsoTicketService $tickets): JsonResponse
    {
        $validated = $request->validate([
            'client' => ['required', 'string', Rule::in(array_keys((array) config('sso.clients', [])))],
            'ticket' => ['required', 'string', 'size:64'],
            'code_verifier' => ['nullable', 'string', 'between:43,128', 'regex:/^[A-Za-z0-9._~-]+$/'],
        ]);

        try {
            $identity = $tickets->exchange(
                $validated['client'],
                $validated['ticket'],
                $request->header('X-SSO-Client-Secret'),
                $validated['code_verifier'] ?? null,
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return $this->error($exception->getMessage(), null, 401);
        }

        $data = ['identity' => $identity];
        $configuration = config("sso.clients.{$validated['client']}", []);
        if (is_array($configuration) && ($configuration['issue_api_token'] ?? false)) {
            $user = User::query()->find($identity['id']);
            if (! $user) {
                return $this->error('SSO user no longer exists.', null, 401);
            }

            $data['user'] = $user;
            $data['token'] = $user->createToken("sso:{$validated['client']}")->plainTextToken;
        }

        return $this->success($data, 'SSO ticket exchanged');
    }
}
