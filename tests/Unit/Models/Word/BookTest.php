<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\EducationLevel;
use App\Models\Word\Word;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class BookTest extends TestCase
{
    public function test_book_has_fillable(): void
    {
        $book = new Book;

        $this->assertContains('name', $book->getFillable());
        $this->assertContains('description', $book->getFillable());
    }

    public function test_book_has_casts(): void
    {
        $book = new Book;

        $this->assertSame('integer', $book->getCasts()['difficulty']);
        $this->assertSame('integer', $book->getCasts()['total_words']);
    }

    public function test_category_relation_is_configured(): void
    {
        $relation = (new Book)->category();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Category::class, $relation->getRelated());
    }

    public function test_words_relation_is_configured(): void
    {
        $relation = (new Book)->words();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertInstanceOf(Word::class, $relation->getRelated());
        $this->assertSame('word_book_word', $relation->getTable());
    }

    public function test_education_levels_relation_is_configured(): void
    {
        $relation = (new Book)->educationLevels();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertInstanceOf(EducationLevel::class, $relation->getRelated());
        $this->assertSame('word_book_education_level', $relation->getTable());
    }

    public function test_update_word_count_updates_total_words(): void
    {
        $category = Category::create([
            'name' => 'Test Category',
            'sort_order' => 1,
        ]);
        $book = Book::create([
            'word_category_id' => $category->id,
            'name' => 'Test Book',
            'difficulty' => 1,
            'total_words' => 0,
            'sort_order' => 0,
        ]);
        $wordA = Word::create(['content' => 'alpha']);
        $wordB = Word::create(['content' => 'beta']);

        $book->words()->attach([$wordA->id, $wordB->id]);
        $book->updateWordCount();

        $this->assertSame(2, $book->fresh()->total_words);
    }
}
