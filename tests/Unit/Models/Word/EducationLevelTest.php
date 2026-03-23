<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\Book;
use App\Models\Word\EducationLevel;
use App\Models\Word\Word;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class EducationLevelTest extends TestCase
{
    public function test_education_level_has_fillable(): void
    {
        $level = new EducationLevel;

        $this->assertContains('code', $level->getFillable());
        $this->assertContains('name', $level->getFillable());
    }

    public function test_education_level_has_casts(): void
    {
        $level = new EducationLevel;

        $this->assertSame('integer', $level->getCasts()['sort_order']);
    }

    public function test_words_relation_is_configured(): void
    {
        $relation = (new EducationLevel)->words();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertInstanceOf(Word::class, $relation->getRelated());
        $this->assertSame('word_education_level', $relation->getTable());
    }

    public function test_books_relation_is_configured(): void
    {
        $relation = (new EducationLevel)->books();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertInstanceOf(Book::class, $relation->getRelated());
        $this->assertSame('word_book_education_level', $relation->getTable());
    }
}
