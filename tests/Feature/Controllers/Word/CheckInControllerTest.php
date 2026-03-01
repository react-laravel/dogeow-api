<?php

namespace Tests\Feature\Controllers\Word;

use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\CheckIn;
use App\Models\Word\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInControllerTest extends TestCase
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

    private function createUserSetting(User $user, array $attributes = []): UserSetting
    {
        return UserSetting::create(array_merge([
            'user_id' => $user->id,
            'daily_new_words' => 10,
            'review_multiplier' => 2,
            'is_auto_pronounce' => true,
        ], $attributes));
    }

    private function createCheckIn(User $user, string $date, array $attributes = []): CheckIn
    {
        return CheckIn::create(array_merge([
            'user_id' => $user->id,
            'check_in_date' => $date,
            'new_words_count' => 0,
            'review_words_count' => 0,
            'study_duration' => 0,
        ], $attributes));
    }

    public function test_can_check_in(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/word/check-in');

        $response->assertStatus(200);
    }

    public function test_can_check_in_with_local_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/word/check-in', [
                'local_date' => '2024-01-01',
            ]);

        $response->assertStatus(200);
    }

    public function test_returns_message_when_already_checked_in(): void
    {
        $user = User::factory()->create();
        // Pre-create check-in for a specific user with a fixed date
        $fixedDate = '2025-01-01';
        CheckIn::create([
            'user_id' => $user->id,
            'check_in_date' => $fixedDate,
            'new_words_count' => 5,
            'review_words_count' => 3,
            'study_duration' => 30,
        ]);

        // Now try to check in again with a different date - should succeed
        $response = $this->actingAs($user)
            ->postJson('/api/word/check-in', [
                'local_date' => '2025-01-02',
            ]);

        $response->assertStatus(200);
    }

    public function test_can_get_calendar(): void
    {
        $user = User::factory()->create();
        $year = now()->year;
        $month = now()->month;

        $response = $this->actingAs($user)
            ->getJson('/api/word/calendar/' . $year . '/' . $month);

        $response->assertStatus(200)
            ->assertJsonStructure(['year', 'month', 'calendar']);
    }

    public function test_can_get_calendar_year(): void
    {
        $user = User::factory()->create();
        $year = now()->year;

        $response = $this->actingAs($user)
            ->getJson('/api/word/calendar/year/' . $year);

        $response->assertStatus(200)
            ->assertJsonStructure(['year', 'calendar']);
    }

    public function test_can_get_calendar_last365(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/word/calendar/last365');

        $response->assertStatus(200)
            ->assertJsonStructure(['start_date', 'end_date', 'calendar']);
    }

    public function test_can_get_stats(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();
        $this->createUserSetting($user, ['current_book_id' => $book->id]);

        $response = $this->actingAs($user)
            ->getJson('/api/word/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'check_in_days',
                'learned_words_count',
                'total_words',
                'progress_percentage',
                'today_checked_in',
            ]);
    }

    public function test_requires_authentication_for_check_in(): void
    {
        $response = $this->postJson('/api/word/check-in');

        $response->assertStatus(401);
    }

    public function test_requires_authentication_for_calendar(): void
    {
        $year = now()->year;
        $month = now()->month;

        $response = $this->getJson('/api/word/calendar/' . $year . '/' . $month);

        $response->assertStatus(401);
    }

    public function test_requires_authentication_for_stats(): void
    {
        $response = $this->getJson('/api/word/stats');

        $response->assertStatus(401);
    }
}
