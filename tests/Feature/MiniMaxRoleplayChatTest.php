<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MiniMaxRoleplayChatTest extends TestCase
{
    public function test_roleplay_chat_returns_reply_from_m2_her(): void
    {
        config([
            'services.minimax.balance_api_key' => 'test-minimax-balance-key',
            'services.minimax.api_base_url' => 'https://api.minimaxi.com',
            'services.minimax.roleplay_model' => 'M2-her',
        ]);

        Http::fake([
            'https://api.minimaxi.com/v1/text/chatcompletion_v2' => Http::response([
                'base_resp' => [
                    'status_code' => 0,
                    'status_msg' => 'success',
                ],
                'model' => 'M2-her',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => '军师已在此，愿闻其详。',
                        ],
                    ],
                ],
                'usage' => [
                    'total_tokens' => 123,
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/minimax/roleplay', [
            'character_name' => '诸葛亮',
            'character_prompt' => '你是《三国演义》中的诸葛亮，智慧、沉稳、善于谋略。',
            'user_persona' => '你是一位来自现代的穿越者。',
            'scene' => '三国时期的隆中对话',
            'message' => '军师，我想和您聊聊治国之道。',
            'history' => [
                [
                    'role' => 'assistant',
                    'content' => '既来之，则安之。',
                ],
                [
                    'role' => 'user',
                    'content' => '我正有此意。',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '角色对话生成成功')
            ->assertJsonPath('data.reply', '军师已在此，愿闻其详。')
            ->assertJsonPath('data.model', 'M2-her')
            ->assertJsonPath('data.character_name', '诸葛亮')
            ->assertJsonPath('data.usage.total_tokens', 123);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.minimaxi.com/v1/text/chatcompletion_v2'
                && $request->hasHeader('Authorization', 'Bearer test-minimax-balance-key')
                && $payload['model'] === 'M2-her'
                && $payload['messages'][0]['role'] === 'system'
                && $payload['messages'][1]['role'] === 'user_system'
                && $payload['messages'][2]['role'] === 'group'
                && $payload['messages'][count($payload['messages']) - 1]['content'] === '军师，我想和您聊聊治国之道。';
        });
    }

    public function test_roleplay_chat_validates_required_fields(): void
    {
        $response = $this->postJson('/api/minimax/roleplay', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'character_name',
                'character_prompt',
                'message',
            ]);
    }
}
