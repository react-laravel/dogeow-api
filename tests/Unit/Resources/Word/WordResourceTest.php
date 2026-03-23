<?php

namespace Tests\Unit\Resources\Word;

use App\Http\Resources\Word\WordResource;
use App\Models\Word\Word;
use Tests\TestCase;

class WordResourceTest extends TestCase
{
    public function test_word_resource_to_array(): void
    {
        $word = new Word;
        $word->id = 1;
        $word->content = 'hello';
        $word->phonetic_us = '/həˈloʊ/';
        $word->explanation = 'A greeting';
        $word->user_id = 1;

        $resource = new WordResource($word);
        $array = $resource->toArray(request());

        $this->assertEquals(1, $array['id']);
        $this->assertEquals('hello', $array['content']);
    }

    public function test_word_resource_includes_phonetic(): void
    {
        $word = new Word;
        $word->id = 1;
        $word->content = 'test';
        $word->phonetic_us = '/test/';
        $word->user_id = 1;

        $resource = new WordResource($word);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('phonetic_us', $array);
    }

    public function test_word_resource_includes_explanation(): void
    {
        $word = new Word;
        $word->id = 1;
        $word->content = 'test';
        $word->explanation = 'A test word';
        $word->user_id = 1;

        $resource = new WordResource($word);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('explanation', $array);
    }
}
