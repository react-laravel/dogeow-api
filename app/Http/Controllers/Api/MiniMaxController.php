<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MiniMax\RoleplayChatRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MiniMaxController extends Controller
{
    private const ROLEPLAY_TIMEOUT_SECONDS = 30;

    public function __construct()
    {
        // MiniMax 国内版 API
    }

    /**
     * M2-her 角色对话
     * POST /api/minimax/roleplay
     */
    public function roleplayChat(RoleplayChatRequest $request): JsonResponse
    {
        $apiKey = $this->getBalanceApiKey();
        $apiBaseUrl = rtrim((string) config('services.minimax.api_base_url'), '/');
        $model = (string) config('services.minimax.roleplay_model', 'M2-her');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax Balance API Key 未配置，请设置 MINIMAX_BALANCE_API_KEY',
            ], 500);
        }

        $validated = $request->validated();
        $characterName = trim((string) $validated['character_name']);
        $characterPrompt = trim((string) $validated['character_prompt']);
        $userPersona = isset($validated['user_persona']) ? trim((string) $validated['user_persona']) : null;
        $scene = isset($validated['scene']) ? trim((string) $validated['scene']) : null;
        $currentMessage = trim((string) $validated['message']);
        $history = array_values($validated['history'] ?? []);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(self::ROLEPLAY_TIMEOUT_SECONDS)
                ->post($apiBaseUrl . '/v1/text/chatcompletion_v2', [
                    'model' => $model,
                    'messages' => $this->buildRoleplayMessages(
                        $characterName,
                        $characterPrompt,
                        $userPersona,
                        $scene,
                        $history,
                        $currentMessage
                    ),
                    'temperature' => 1.0,
                    'top_p' => 0.95,
                    'max_completion_tokens' => 1024,
                    'stream' => false,
                ]);

            if ($response->failed()) {
                Log::warning('[MiniMax] 角色对话请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $this->extractMiniMaxErrorMessage($response->json(), $response->body()),
                ], $response->status());
            }

            $payload = $response->json();
            $baseRespCode = (int) data_get($payload, 'base_resp.status_code', 0);

            if ($baseRespCode !== 0) {
                Log::warning('[MiniMax] 角色对话返回业务错误', [
                    'payload' => $payload,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $this->extractMiniMaxErrorMessage($payload, $response->body()),
                ], 502);
            }

            $reply = trim((string) data_get($payload, 'choices.0.message.content', ''));

            if ($reply === '') {
                Log::warning('[MiniMax] 角色对话返回空内容', [
                    'payload' => $payload,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'MiniMax 未返回有效回复内容',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => '角色对话生成成功',
                'data' => [
                    'reply' => $reply,
                    'model' => (string) data_get($payload, 'model', $model),
                    'usage' => data_get($payload, 'usage'),
                    'character_name' => $characterName,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[MiniMax] 角色对话异常', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '角色对话生成失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取 MiniMax 订阅用量信息
     * GET /api/minimax/subscription
     */
    public function subscription(): JsonResponse
    {
        $apiKey = $this->getTokenApiKey();

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax Token API Key 未配置，请设置 MINIMAX_TOKEN_API_KEY',
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->get('https://www.minimaxi.com/v1/api/openplatform/coding_plan/remains');

            if ($response->failed()) {
                Log::warning('[MiniMax] 订阅信息请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '请求 MiniMax API 失败: ' . $response->status(),
                    'data' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('[MiniMax] 订阅信息异常', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '获取订阅信息失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取 MiniMax 套餐到期时间
     * GET /api/minimax/subscription-detail
     */
    public function subscriptionDetail(): JsonResponse
    {
        $apiKey = $this->getTokenApiKey();
        $groupId = config('services.minimax.group_id');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax Token API Key 未配置，请设置 MINIMAX_TOKEN_API_KEY',
            ], 500);
        }

        if (empty($groupId)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax Group ID 未配置，请在 .env 中设置 MINIMAX_GROUP_ID',
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->get('https://www.minimaxi.com/v1/api/openplatform/charge/combo/cycle_audio_resource_package', [
                'biz_line' => 2,
                'cycle_type' => 1,
                'resource_package_type' => 7,
                'GroupId' => $groupId,
            ]);

            if ($response->failed()) {
                Log::warning('[MiniMax] 套餐详情请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '请求 MiniMax API 失败: ' . $response->status(),
                    'data' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('[MiniMax] 套餐详情异常', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '获取套餐详情失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取 MiniMax Token 消耗账单
     * GET /api/minimax/billing
     */
    public function billing(): JsonResponse
    {
        try {
            $apiKey = $this->getBalanceApiKey();
            $groupId = config('services.minimax.group_id');

            Log::info('[MiniMax] billing 配置检查', [
                'apiKey_exists' => ! empty($apiKey),
                'apiKey_length' => $apiKey ? strlen($apiKey) : 0,
                'groupId' => $groupId,
                'services_config' => config('services.minimax'),
            ]);

            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'MiniMax Balance API Key 未配置，请设置 MINIMAX_BALANCE_API_KEY',
                ], 500);
            }

            if (empty($groupId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'MiniMax Group ID 未配置，请在 .env 中设置 MINIMAX_GROUP_ID',
                ], 500);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->get('https://www.minimaxi.com/account/amount', [
                'page' => 1,
                'limit' => 100,
                'aggregate' => false,
                'GroupId' => $groupId,
            ]);

            if ($response->failed()) {
                Log::warning('[MiniMax] 账单请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '请求 MiniMax API 失败: ' . $response->status(),
                    'data' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('[MiniMax] 账单异常', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => '获取账单信息失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<int, array{role:string, content:string}>  $history
     * @return array<int, array<string, string>>
     */
    private function buildRoleplayMessages(
        string $characterName,
        string $characterPrompt,
        ?string $userPersona,
        ?string $scene,
        array $history,
        string $currentMessage
    ): array {
        $messages = [
            [
                'role' => 'system',
                'name' => $characterName,
                'content' => $characterPrompt,
            ],
        ];

        if (! empty($userPersona)) {
            $messages[] = [
                'role' => 'user_system',
                'name' => '用户',
                'content' => $userPersona,
            ];
        }

        if (! empty($scene)) {
            $messages[] = [
                'role' => 'group',
                'content' => $scene,
            ];
        }

        foreach ($history as $item) {
            $content = trim((string) ($item['content'] ?? ''));

            if ($content === '') {
                continue;
            }

            $role = ($item['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';

            $messages[] = [
                'role' => $role,
                'name' => $role === 'assistant' ? $characterName : '用户',
                'content' => $content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'name' => '用户',
            'content' => $currentMessage,
        ];

        return $messages;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function extractMiniMaxErrorMessage(?array $payload, string $fallback): string
    {
        $message = data_get($payload, 'base_resp.status_msg')
            ?? data_get($payload, 'message')
            ?? data_get($payload, 'error.message')
            ?? data_get($payload, 'error');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return $fallback !== '' ? $fallback : '请求 MiniMax 角色对话接口失败';
    }

    private function getTokenApiKey(): ?string
    {
        $apiKey = config('services.minimax.token_api_key');

        return is_string($apiKey) && $apiKey !== '' ? $apiKey : null;
    }

    private function getBalanceApiKey(): ?string
    {
        $apiKey = config('services.minimax.balance_api_key');

        return is_string($apiKey) && $apiKey !== '' ? $apiKey : null;
    }
}
