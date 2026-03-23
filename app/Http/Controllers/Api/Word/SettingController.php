<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Word\UpdateSettingRequest;
use App\Models\Word\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
    /**
     * 获取用户设置
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'daily_new_words' => 10,
                'review_multiplier' => 2,
                'is_auto_pronounce' => true,
            ]
        );

        $setting->load('currentBook');

        return response()->json($setting);
    }

    /**
     * 更新设置
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $user = Auth::user();
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'daily_new_words' => 10,
                'review_multiplier' => 2,
                'is_auto_pronounce' => true,
            ]
        );

        $setting->update($request->validated());
        $setting->load('currentBook');

        return response()->json([
            'message' => '设置更新成功',
            'setting' => $setting,
        ]);
    }
}
