<?php

namespace Tests\Feature\Controllers\Word;

use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\EducationLevel;
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

    private function createEducationLevel(array $attributes = []): EducationLevel
    {
        return EducationLevel::create(array_merge([
            'code' => 'primary',
            'name' => 'Primary',
            'sort_order' => 1,
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
        $word = $this->createWord(['content' => 'apple']);
        $book->words()->attach($word->id, ['sort_order' => 1]);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/daily');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertEquals('apple', $data[0]['content']);
    }

    public function test_get_daily_words_returns_empty_when_no_book(): void
    {
        $user = User::factory()->create();
        $this->createUserSetting($user);

        $response = $this->actingAs($user)
            ->getJson('/api/word/daily');

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
    }

    public function test_get_daily_words_returns_empty_when_current_book_does_not_exist(): void
    {
        $user = User::factory()->create();
        $this->createUserSetting($user, ['current_book_id' => 999999]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/daily');

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_can_get_review_words(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord(['content' => 'review']);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        // Create a user word that needs review (status 1-3, next_review_at in past)
        UserWord::create([
            'user_id' => $user->id,
            'word_id' => $word->id,
            'word_book_id' => $book->id,
            'status' => 1,
            'stage' => 1,
            'ease_factor' => 2.50,
            'next_review_at' => now()->subDay(),
            'review_count' => 0,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/review');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
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
        $response->assertJsonPath('message', '单词标记成功');

        $this->assertDatabaseHas('word_user_words', [
            'user_id' => $user->id,
            'word_id' => $word->id,
            'word_book_id' => $book->id,
        ]);
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
        $response->assertJsonPath('message', '单词标记成功');

        $userWord = UserWord::where('user_id', $user->id)
            ->where('word_id', $word->id)
            ->first();
        $this->assertNotNull($userWord);
        $this->assertGreaterThan(0, $userWord->review_count);
    }

    public function test_mark_word_initializes_existing_pending_user_word(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $word = $this->createWord();
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $userWord = UserWord::create([
            'user_id' => $user->id,
            'word_id' => $word->id,
            'word_book_id' => $book->id,
            'status' => 0,
            'stage' => 5,
            'ease_factor' => 1.3,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/word/mark/' . $word->id, [
                'remembered' => true,
            ]);

        $response->assertStatus(200);
        $userWord->refresh();
        $this->assertGreaterThanOrEqual(1, $userWord->stage);
        $this->assertGreaterThan(1.3, $userWord->ease_factor);
        $this->assertSame(1, $userWord->review_count);
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
        $response->assertJsonPath('message', '已设为简单词');

        $userWord = UserWord::where('user_id', $user->id)
            ->where('word_id', $word->id)
            ->first();
        $this->assertNotNull($userWord);
        $this->assertSame(4, $userWord->status);
        $this->assertNull($userWord->next_review_at);
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
        $response->assertJsonStructure([
            'data' => [
                'total_words',
                'learned_words',
                'mastered_words',
                'difficult_words',
                'simple_words',
                'progress_percentage',
            ],
        ]);
    }

    public function test_get_progress_returns_zero_when_no_book(): void
    {
        $user = User::factory()->create();
        $this->createUserSetting($user);

        $response = $this->actingAs($user)
            ->getJson('/api/word/progress');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_words', 0);
        $response->assertJsonPath('data.learned_words', 0);
        $response->assertJsonPath('data.progress_percentage', 0);
    }

    public function test_get_progress_returns_not_found_when_current_book_is_missing(): void
    {
        $user = User::factory()->create();
        $this->createUserSetting($user, ['current_book_id' => 999999]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/progress');

        $response->assertStatus(404)
            ->assertJsonPath('message', '单词书不存在');
    }

    public function test_get_progress_returns_zero_percentage_when_book_has_no_words(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook(['total_words' => 0]);
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/progress');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_words', 0)
            ->assertJsonPath('data.progress_percentage', 0);
    }

    public function test_can_search_word(): void
    {
        $user = User::factory()->create();
        $word = $this->createWord(['content' => 'hello']);

        $response = $this->actingAs($user)
            ->getJson('/api/word/search/hello');

        $response->assertStatus(200);
        $response->assertJsonPath('data.found', true);
        $response->assertJsonPath('data.word.content', 'hello');
    }

    public function test_search_word_returns_not_found(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/word/search/nonexistentword');

        $response->assertStatus(200)
            ->assertJsonPath('data.found', false);
    }

    public function test_search_word_requires_non_empty_keyword(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/word/search/%20%20');

        $response->assertStatus(422)
            ->assertJsonPath('message', '请输入搜索关键词');
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
        $response->assertJsonPath('message', '单词更新成功');
        $response->assertJsonPath('data.word.explanation', 'Updated explanation');
    }

    public function test_can_create_word_and_attach_books_by_education_level(): void
    {
        $user = User::factory()->create();
        $level = $this->createEducationLevel([
            'code' => 'ielts',
            'name' => 'IELTS',
        ]);
        $book = $this->createBook(['total_words' => 0]);
        $book->educationLevels()->attach($level->id);

        $response = $this->actingAs($user)
            ->postJson('/api/word/create', [
                'content' => 'aberration',
                'phonetic_us' => '/ˌæbəˈreɪʃən/',
                'explanation' => 'something that differs',
                'example_sentences' => [
                    ['en' => 'It was an aberration.', 'zh' => '那是个例外。'],
                ],
                'education_level_codes' => ['ielts'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', '单词创建成功')
            ->assertJsonPath('data.word.content', 'aberration');

        $word = Word::where('content', 'aberration')->firstOrFail();
        $this->assertDatabaseHas('word_education_level', [
            'word_id' => $word->id,
            'education_level_id' => $level->id,
        ]);
        $this->assertDatabaseHas('word_book_word', [
            'word_book_id' => $book->id,
            'word_id' => $word->id,
            'sort_order' => 1,
        ]);
        $this->assertSame(1, $book->fresh()->total_words);
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
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('content', $data[0]);
        $this->assertArrayHasKey('example_sentences', $data[0]);
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
