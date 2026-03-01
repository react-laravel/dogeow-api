<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\UserWord;
use Tests\TestCase;

class UserWordTest extends TestCase
{
    public function test_user_word_has_fillable(): void
    {
        $userWord = new UserWord;

        $this->assertContains('user_id', $userWord->getFillable());
        $this->assertContains('word_id', $userWord->getFillable());
    }

    public function test_user_word_has_casts(): void
    {
        $userWord = new UserWord;

        $this->assertArrayHasKey('id', $userWord->getCasts());
    }

    public function test_user_word_has_relationships(): void
    {
        $userWord = new UserWord;

        $this->assertTrue(method_exists($userWord, 'user'));
        $this->assertTrue(method_exists($userWord, 'word'));
    }
}
