<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * 获取当前用户 ID 的 Trait
 *
 * 如果用户已登录返回真实用户 ID，否则抛出异常。
 * 注意：使用此 trait 的路由必须已受 auth:sanctum 中间件保护。
 */
trait GetCurrentUserId
{
    /**
     * 获取当前用户 ID，未登录时抛出异常
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function getCurrentUserId(): int
    {
        if (! Auth::check()) {
            abort(401, 'Unauthenticated');
        }

        return Auth::id();
    }

    /**
     * 检查当前用户是否已登录
     */
    protected function isAuthenticated(): bool
    {
        return Auth::check();
    }
}
