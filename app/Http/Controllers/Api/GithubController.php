<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
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
            'url' => Socialite::driver('github')
                ->stateless() // @phpstan-ignore method.notFound
                ->scopes(['read:user', 'user:email'])
                ->redirect()
                ->getTargetUrl(),
        ]);
    }

    /**
     * GitHub 回调处理（后端直接被 GitHub 调用的场景，保留兼容）
     * Token 通过 Session 传递，避免出现在 URL 中造成泄露
     */
    public function callback()
    {
        $githubUser = Socialite::driver('github')->stateless()->user(); // @phpstan-ignore method.notFound

        [$user, $token] = $this->findOrCreateUser($githubUser);

        $frontendUrl = config('services.github.redirect');
        $baseUrl = preg_replace('#/auth/github/callback$#', '', $frontendUrl);

        Session::put('github_oauth_token', $token);
        Session::put('github_oauth_user', $user);

        return redirect($baseUrl);
    }

    /**
     * 前端用 authorization code 换取 token（GitHub 回调到前端后，前端 POST code 到这里）
     */
    public function exchange(Request $request): JsonResponse
    {
        $code = $request->input('code');

        if (! is_string($code) || trim($code) === '') {
            return response()->json([
                'success' => false,
                'message' => '缺少授权码',
            ], 422);
        }

        try {
            $response = Http::asForm()->post('https://github.com/login/oauth/access_token', [
                'client_id' => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'code' => $code,
            ]);

            $data = $response->json();

            if (isset($data['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $data['error_description'] ?? 'GitHub 授权失败',
                ], 401);
            }

            $accessToken = $data['access_token'] ?? null;
            if (! $accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => '未获取到 access token',
                ], 401);
            }

            $githubUserResponse = Http::withToken($accessToken)
                ->get('https://api.github.com/user');

            if (! $githubUserResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => '获取 GitHub 用户信息失败',
                ], 401);
            }

            $githubData = $githubUserResponse->json();
            $githubUser = new \Laravel\Socialite\Two\User;
            $githubUser->map($githubData);

            [$user, $token] = $this->findOrCreateUser($githubUser);

            return response()->json([
                'token' => $token,
                'user' => $user,
            ]);
        } catch (\Throwable $e) {
            Log::error('GitHub OAuth exchange failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => '登录处理失败',
            ], 500);
        }
    }

    /**
     * 查找或创建用户，返回 [userArray, plainTextToken]
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function findOrCreateUser(\Laravel\Socialite\Two\User $githubUser): array
    {
        $githubId = (string) ($githubUser->id ?? '');

        $user = User::firstOrCreate(
            ['github_id' => $githubId],
            [
                'name' => $githubUser->name !== null ? $githubUser->name : $githubUser->nickname,
                'email' => $githubUser->email,
                'password' => Hash::make(Str::random(24)),
                'github_avatar' => $githubUser->avatar,
            ]
        );

        if (! $user->github_id) {
            $user->update(['github_id' => $githubId]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $userArray = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'github_id' => $user->github_id,
            'github_avatar' => $user->github_avatar,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        return [$userArray, $token];
    }
}
