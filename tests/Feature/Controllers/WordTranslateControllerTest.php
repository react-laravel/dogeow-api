<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WordTranslateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_translate_requires_authentication(): void
    {
        $response = $this->postJson('/api/word/translate', [
            'text' => 'hello',
        ]);

        $response->assertUnauthorized();
    }

    public function test_translate_success(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([
                'responseData' => [
                    'translatedText' => '你好',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/word/translate', [
            'text' => 'hello',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'text' => '你好',
            ],
        ]);
    }

    public function test_translate_validates_text(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/word/translate', []);

        $response->assertStatus(422);
    }

    public function test_translate_upstream_failure(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake([
            'api.mymemory.translated.net/*' => Http::response([], 500),
        ]);

        $response = $this->postJson('/api/word/translate', [
            'text' => 'hello',
        ]);

        $response->assertStatus(502);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
