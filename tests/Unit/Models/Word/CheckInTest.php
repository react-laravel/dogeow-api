<?php

namespace Tests\Unit\Models\Word;

use App\Models\Word\CheckIn;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    public function test_check_in_has_fillable(): void
    {
        $checkIn = new CheckIn;

        $this->assertContains('user_id', $checkIn->getFillable());
    }

    public function test_check_in_has_casts(): void
    {
        $checkIn = new CheckIn;

        $this->assertSame('date', $checkIn->getCasts()['check_in_date']);
        $this->assertSame('integer', $checkIn->getCasts()['new_words_count']);
    }

    public function test_check_in_belongs_to_user(): void
    {
        $checkIn = new CheckIn;

        $this->assertInstanceOf(BelongsTo::class, $checkIn->user());
    }

    public function test_check_in_uses_correct_table(): void
    {
        $checkIn = new CheckIn;

        $this->assertSame('user_word_check_ins', $checkIn->getTable());
    }

    public function test_check_in_fillable_includes_all_required_fields(): void
    {
        $checkIn = new CheckIn;
        $fillable = $checkIn->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('check_in_date', $fillable);
        $this->assertContains('new_words_count', $fillable);
        $this->assertContains('review_words_count', $fillable);
        $this->assertContains('study_duration', $fillable);
    }

    public function test_check_in_casts_all_numeric_fields(): void
    {
        $checkIn = new CheckIn;
        $casts = $checkIn->getCasts();

        $this->assertArrayHasKey('new_words_count', $casts);
        $this->assertArrayHasKey('review_words_count', $casts);
        $this->assertArrayHasKey('study_duration', $casts);
        $this->assertSame('integer', $casts['new_words_count']);
        $this->assertSame('integer', $casts['review_words_count']);
        $this->assertSame('integer', $casts['study_duration']);
    }
}
