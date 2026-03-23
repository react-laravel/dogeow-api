<?php

namespace Tests\Unit\Models\Word;

use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\UserWord;
use App\Models\Word\Word;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

        $this->assertSame('integer', $userWord->getCasts()['status']);
        $this->assertSame('boolean', $userWord->getCasts()['is_favorite']);
    }

    public function test_user_relation_is_configured(): void
    {
        $relation = (new UserWord)->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(User::class, $relation->getRelated());
    }

    public function test_word_relation_is_configured(): void
    {
        $relation = (new UserWord)->word();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Word::class, $relation->getRelated());
    }

    public function test_book_relation_is_configured(): void
    {
        $relation = (new UserWord)->book();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Book::class, $relation->getRelated());
    }
}
