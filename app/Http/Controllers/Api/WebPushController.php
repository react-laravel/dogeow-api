<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebPush\PushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushController extends Controller
{
    /**
     * 返回 VAPID 公钥，供前端 PushManager.subscribe 使用。
     * 公开接口，无需登录。
     */
    public function vapidKey(): JsonResponse
    {
        $key = config('webpush.public_key');
        if (empty($key)) {
            return $this->error('服务端未配置 VAPID 公钥，请运行 php artisan webpush:vapid', [], 500);
        }

        return response()->json(['public_key' => $key]);
    }

    /**
     * 保存或更新当前用户的推送订阅。
     */
    public function updateSubscription(PushSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $endpoint = $validated['endpoint'];
        $key = $validated['keys']['p256dh'] ?? null;
        $token = $validated['keys']['auth'] ?? null;

        $user->updatePushSubscription($endpoint, $key, $token, null);

        return $this->success([], '推送订阅已保存');
    }

    /**
     * 删除当前用户的指定推送订阅。
     */
    public function deleteSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:2000',
        ]);

        $request->user()->deletePushSubscription($request->input('endpoint'));

        return $this->success([], '推送订阅已删除');
    }
}
