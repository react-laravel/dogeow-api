<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Word\TranslateRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class TranslateController extends Controller
{
    private const MYMEMORY_ENDPOINT = 'https://api.mymemory.translated.net/get';

    /**
     * Proxy English→Chinese translation via MyMemory (avoids browser CORS / quota exposure).
     */
    public function translate(TranslateRequest $request): JsonResponse
    {
        $text = trim((string) $request->validated('text'));
        $langpair = (string) ($request->validated('langpair') ?? 'en|zh');

        if ($text === '') {
            return response()->json([
                'success' => true,
                'data' => ['text' => ''],
            ]);
        }

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get(self::MYMEMORY_ENDPOINT, [
                    'q' => $text,
                    'langpair' => $langpair,
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'success' => false,
                'message' => '翻译服务暂时不可用',
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => '翻译服务请求失败',
            ], 502);
        }

        $translated = data_get($response->json(), 'responseData.translatedText');
        if (! is_string($translated) || $translated === '') {
            return response()->json([
                'success' => false,
                'message' => '翻译结果无效',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'text' => $translated,
            ],
        ]);
    }
}
