<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * Display the user's profile information.
     */
    public function edit(Request $request): JsonResponse
    {
        return $this->success([
            'user' => $request->user()->only(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return $this->success([
            'user' => $request->user()->only(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
        ], 'Profile updated successfully');
    }

    /**
     * Delete the user's account and related data.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user) {
            // 删除相关的 Item 及其图片
            $user->items()->each(function ($item) {
                $item->images()->delete();
                $item->delete();
            });

            $user->delete();
        });

        return $this->success([], 'Account deleted successfully');
    }
}
