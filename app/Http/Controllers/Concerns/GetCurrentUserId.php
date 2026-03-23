<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * 获取当前用户 ID 的 Trait
 *
 * 如果用户已登录返回真实用户 ID，否则返回默认用户 ID (1)
 * 用于处理云存储等允许未登录用户使用的基础功能
 */
trait GetCurrentUserId
{
    /**
     * 获取当前用户 ID，未登录时返回默认用户 ID
     */
    protected function getCurrentUserId(): int
    {
        return Auth::check() ? Auth::id() : 1;
    }

    /**
     * 检查当前用户是否已登录
     */
    protected function isAuthenticated(): bool
    {
        return Auth::check();
    }
}
