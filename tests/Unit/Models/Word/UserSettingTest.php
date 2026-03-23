<?php

namespace Tests\Unit\Models\Word;

use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\UserSetting;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class UserSettingTest extends TestCase
{
    public function test_user_setting_has_fillable(): void
    {
        $setting = new UserSetting;

        $this->assertContains('user_id', $setting->getFillable());
    }

    public function test_user_setting_has_casts(): void
    {
        $setting = new UserSetting;

        $this->assertSame('integer', $setting->getCasts()['daily_new_words']);
        $this->assertSame('boolean', $setting->getCasts()['is_auto_pronounce']);
    }

    public function test_user_setting_relationships_are_configured(): void
    {
        $setting = new UserSetting;

        $this->assertInstanceOf(BelongsTo::class, $setting->user());
        $this->assertInstanceOf(BelongsTo::class, $setting->currentBook());
    }

    public function test_user_setting_has_correct_table(): void
    {
        $setting = new UserSetting;

        $this->assertSame('user_word_settings', $setting->getTable());
    }

    public function test_user_setting_fillable_includes_all_fields(): void
    {
        $setting = new UserSetting;
        $fillable = $setting->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('current_book_id', $fillable);
        $this->assertContains('daily_new_words', $fillable);
    }

    public function test_user_setting_casts_all_boolean_fields(): void
    {
        $setting = new UserSetting;
        $casts = $setting->getCasts();

        $this->assertArrayHasKey('is_auto_pronounce', $casts);
        $this->assertSame('boolean', $casts['is_auto_pronounce']);
    }

    public function test_user_setting_casts_all_integer_fields(): void
    {
        $setting = new UserSetting;
        $casts = $setting->getCasts();

        $this->assertArrayHasKey('daily_new_words', $casts);
        $this->assertArrayHasKey('review_multiplier', $casts);
    }

    public function test_user_relationship_returns_user_model(): void
    {
        $setting = new UserSetting;
        $relation = $setting->user();

        $this->assertInstanceOf(User::class, $relation->getRelated());
    }

    public function test_current_book_relationship_returns_book_model(): void
    {
        $setting = new UserSetting;
        $relation = $setting->currentBook();

        $this->assertInstanceOf(Book::class, $relation->getRelated());
    }
}
