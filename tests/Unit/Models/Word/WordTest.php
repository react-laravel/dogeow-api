<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\Book;
use App\Models\Word\EducationLevel;
use App\Models\Word\UserWord;
use App\Models\Word\Word;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

        $this->assertSame('array', $word->getCasts()['example_sentences']);
        $this->assertSame('integer', $word->getCasts()['difficulty']);
    }

    public function test_books_relation_is_configured(): void
    {
        $relation = (new Word)->books();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertInstanceOf(Book::class, $relation->getRelated());
        $this->assertSame('word_book_word', $relation->getTable());
    }

    public function test_user_words_relation_is_configured(): void
    {
        $relation = (new Word)->userWords();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(UserWord::class, $relation->getRelated());
    }

    public function test_education_levels_relation_is_configured(): void
    {
        $relation = (new Word)->educationLevels();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
        $this->assertInstanceOf(EducationLevel::class, $relation->getRelated());
        $this->assertSame('word_education_level', $relation->getTable());
    }

    public function test_find_or_create_by_content_creates_and_reuses_word(): void
    {
        $created = Word::findOrCreateByContent('hello', [
            'explanation' => 'greeting',
            'difficulty' => 1,
        ]);
        $found = Word::findOrCreateByContent('hello', [
            'explanation' => 'changed',
        ]);

        $this->assertTrue($created->is($found));
        $this->assertSame('greeting', $found->explanation);
        $this->assertDatabaseCount('words', 1);
    }
}
