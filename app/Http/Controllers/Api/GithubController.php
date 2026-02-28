<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GithubController extends Controller
{
    /**
     * 跳转到 GitHub 授权页面
     */
    public function redirect(): JsonResponse
    {
        return $this->success([
            // @phpstan-ignore-next-line
            'url' => Socialite::driver('github')
                ->stateless()
                ->scopes(['read:user', 'user:email'])
                ->redirect()
                ->getTargetUrl(),
        ]);
    }

    /**
     * GitHub 回调处理
     */
    public function callback()
    {
        $githubUser = Socialite::driver('github')->stateless()->user(); // @phpstan-ignore method.notFound

        // 根据 github_id 查找用户，不存在则创建
        $user = User::firstOrCreate(
            ['github_id' => $githubUser->id],
            [
                'name' => $githubUser->name ?? $githubUser->nickname,
                'email' => $githubUser->email,
                'password' => Hash::make(Str::random(24)),
                'github_avatar' => $githubUser->avatar,
            ]
        );

        // 如果用户没有 github_id（旧用户），更新它
        if (! $user->github_id) {
            $user->update(['github_id' => $githubUser->id]);
        }

        // 生成 Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        // 重定向回前端并带上 token
        $frontendUrl = config('services.github.redirect');
        // 移除回调路径，保留基础 URL
        $baseUrl = preg_replace('#/auth/github/callback$#', '', $frontendUrl);

        return redirect($baseUrl . '?token=' . $token . '&user=' . urlencode(json_encode($user)));
    }
}
