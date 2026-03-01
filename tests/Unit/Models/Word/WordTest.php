<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\Word;
use Tests\TestCase;

class WordTest extends TestCase
{
    public function test_word_has_fillable(): void
    {
        $word = new Word;

        $this->assertContains('content', $word->getFillable());
    }

    public function test_word_has_casts(): void
    {
        $word = new Word;

        $this->assertArrayHasKey('id', $word->getCasts());
    }
}
