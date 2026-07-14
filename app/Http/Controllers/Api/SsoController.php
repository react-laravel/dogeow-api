<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ]);

        try {
            $result = $tickets->issue($validated['client'], $validated['return_to'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), null, 422);
        }

        return $this->success($result, 'SSO ticket issued');
    }

    public function exchange(Request $request, SsoTicketService $tickets): JsonResponse
    {
        $validated = $request->validate([
            'client' => ['required', 'string', Rule::in(array_keys((array) config('sso.clients', [])))],
            'ticket' => ['required', 'string', 'size:64'],
        ]);

        try {
            $identity = $tickets->exchange(
                $validated['client'],
                $validated['ticket'],
                $request->header('X-SSO-Client-Secret'),
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return $this->error($exception->getMessage(), null, 401);
        }

        return $this->success(['identity' => $identity], 'SSO ticket exchanged');
    }
}
