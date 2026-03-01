<?php

namespace Tests\Feature\Controllers\Word;

use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\UserSetting;
use App\Models\Word\UserWord;
use App\Models\Word\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(array $attributes = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Test Category',
            'description' => 'Test Category Description',
            'sort_order' => 1,
        ], $attributes));
    }

    private function createBook(array $attributes = []): Book
    {
        $category = $this->createCategory();

        return Book::create(array_merge([
            'word_category_id' => $category->id,
            'name' => 'Test Book',
            'description' => 'Test Book Description',
            'difficulty' => 1,
            'total_words' => 10,
            'sort_order' => 1,
        ], $attributes));
    }

    private function createWord(array $attributes = []): Word
    {
        return Word::create(array_merge([
            'content' => 'test',
            'phonetic_us' => '/test/',
            'explanation' => json_encode(['en' => 'test', 'zh' => '测试']),
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ], $attributes));
    }

    private function createUserSetting(User $user, array $attributes = []): UserSetting
    {
        return UserSetting::create(array_merge([
            'user_id' => $user->id,
            'daily_new_words' => 10,
            'review_multiplier' => 2,
            'is_auto_pronounce' => true,
        ], $attributes));
    }

    public function test_can_get_daily_words(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/daily');

        $response->assertStatus(200);
    }

    public function test_get_daily_words_returns_empty_when_no_book(): void
    {
        $user = User::factory()->create();
        $this->createUserSetting($user);

        $response = $this->actingAs($user)
            ->getJson('/api/word/daily');

        $response->assertStatus(200);
    }

    public function test_can_get_review_words(): void
    {
        $user = User::factory()->create();
        $this->createUserSetting($user);

        $response = $this->actingAs($user)
            ->getJson('/api/word/review');

        $response->assertStatus(200);
    }

    public function test_can_mark_word_as_remembered(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/word/mark/' . $word->id, [
                'remembered' => true,
            ]);

        $response->assertStatus(200);
    }

    public function test_can_mark_word_as_not_remembered(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/word/mark/' . $word->id, [
                'remembered' => false,
            ]);

        $response->assertStatus(200);
    }

    public function test_mark_word_validates_remembered_boolean(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/word/mark/' . $word->id, [
                'remembered' => 'not-a-boolean',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_mark_word_as_simple(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $book->words()->attach($word->id, ['sort_order' => 1]);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/word/simple/' . $word->id);

        $response->assertStatus(200);
    }

    public function test_mark_word_as_simple_requires_book(): void
    {
        $user = User::factory()->create();
        $word = $this->createWord();
        $this->createUserSetting($user);

        $response = $this->actingAs($user)
            ->postJson('/api/word/simple/' . $word->id);

        $response->assertStatus(422);
    }

    public function test_can_get_progress(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/progress');

        $response->assertStatus(200);
    }

    public function test_get_progress_returns_zero_when_no_book(): void
    {
        $user = User::factory()->create();
        $this->createUserSetting($user);

        $response = $this->actingAs($user)
            ->getJson('/api/word/progress');

        $response->assertStatus(200);
    }

    public function test_can_search_word(): void
    {
        $user = User::factory()->create();
        $word = $this->createWord(['content' => 'hello']);

        $response = $this->actingAs($user)
            ->getJson('/api/word/search/hello');

        $response->assertStatus(200);
    }

    public function test_search_word_returns_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/word/search/nonexistentword');

        $response->assertStatus(200)
            ->assertJson(['found' => false]);
    }

    public function test_can_update_word(): void
    {
        $user = User::factory()->create();
        $word = $this->createWord();

        $response = $this->actingAs($user)
            ->patchJson('/api/word/' . $word->id, [
                'explanation' => 'Updated explanation',
            ]);

        $response->assertStatus(200);
    }

    public function test_can_get_fill_blank_words(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord([
            'example_sentences' => [
                ['en' => 'This is a test sentence.', 'zh' => '这是一个测试句子。'],
            ],
        ]);
        $book->words()->attach($word->id, ['sort_order' => 1]);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        // Create user word with status 1 (learning)
        UserWord::create([
            'user_id' => $user->id,
            'word_id' => $word->id,
            'word_book_id' => $book->id,
            'status' => 1,
            'stage' => 0,
            'ease_factor' => 2.50,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/fill-blank');

        $response->assertStatus(200);
    }

    public function test_requires_authentication_for_daily_words(): void
    {
        $response = $this->getJson('/api/word/daily');

        $response->assertStatus(401);
    }

    public function test_requires_authentication_for_progress(): void
    {
        $response = $this->getJson('/api/word/progress');

        $response->assertStatus(401);
    }
}
