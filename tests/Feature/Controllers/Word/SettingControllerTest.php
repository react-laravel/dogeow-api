<?php

namespace Tests\Feature\Controllers\Word;

use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingControllerTest extends TestCase
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

    public function test_can_get_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/word/settings');

        $response->assertStatus(200);
    }

    public function test_can_update_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'daily_new_words' => 20,
                'review_multiplier' => 3,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'setting']);
    }

    public function test_can_update_settings_with_current_book(): void
    {
        $user = User::factory()->create();
        $book = $this->createBook();

        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'daily_new_words' => 15,
                'current_book_id' => $book->id,
            ]);

        $response->assertStatus(200);
    }

    public function test_can_update_is_auto_pronounce(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'is_auto_pronounce' => false,
            ]);

        $response->assertStatus(200);
    }

    public function test_update_settings_validates_daily_new_words_min(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'daily_new_words' => 0,
            ]);

        $response->assertStatus(422);
    }

    public function test_update_settings_validates_daily_new_words_max(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'daily_new_words' => 200,
            ]);

        $response->assertStatus(422);
    }

    public function test_update_settings_validates_review_multiplier(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'review_multiplier' => 5,
            ]);

        $response->assertStatus(422);
    }

    public function test_update_settings_validates_review_multiplier_values(): void
    {
        $user = User::factory()->create();

        // Should only accept 1, 2, 3
        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'review_multiplier' => 2,
            ]);

        $response->assertStatus(200);
    }

    public function test_update_settings_validates_current_book_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'current_book_id' => 99999,
            ]);

        $response->assertStatus(422);
    }

    public function test_get_settings_creates_default_if_not_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/word/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user_id',
                'daily_new_words',
                'review_multiplier',
                'is_auto_pronounce',
            ]);
    }

    public function test_update_settings_creates_default_if_not_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/word/settings', [
                'daily_new_words' => 5,
            ]);

        $response->assertStatus(200);
    }

    public function test_requires_authentication_for_get_settings(): void
    {
        $response = $this->getJson('/api/word/settings');

        $response->assertStatus(401);
    }

    public function test_requires_authentication_for_update_settings(): void
    {
        $response = $this->putJson('/api/word/settings', [
            'daily_new_words' => 20,
        ]);

        $response->assertStatus(401);
    }
}
