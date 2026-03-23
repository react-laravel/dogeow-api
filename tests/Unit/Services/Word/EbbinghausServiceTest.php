<?php

namespace Tests\Unit\Services\Word;

use App\Models\Word\UserWord;
use App\Services\Word\EbbinghausService;
use Carbon\Carbon;
use Tests\TestCase;

class EbbinghausServiceTest extends TestCase
{
    private EbbinghausService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-01 12:00:00');
        $this->service = new EbbinghausService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_calculate_next_review_when_remembered(): void
    {
        $userWord = new UserWord;
        $userWord->stage = 0;

        $nextReview = $this->service->calculateNextReview($userWord, true);

        $this->assertEquals(1, $userWord->stage);
    }

    public function test_calculate_next_review_when_not_remembered(): void
    {
        $userWord = new UserWord;
        $userWord->stage = 2;

        $nextReview = $this->service->calculateNextReview($userWord, false);

        $this->assertEquals(1, $userWord->stage);
    }

    public function test_calculate_next_review_max_stage(): void
    {
        $userWord = new UserWord;
        $userWord->stage = 7;

        $nextReview = $this->service->calculateNextReview($userWord, true);

        $this->assertEquals(7, $userWord->stage);
    }

    public function test_update_ease_factor_when_remembered(): void
    {
        $userWord = new UserWord;
        $userWord->ease_factor = 2.5;

        $newEase = $this->service->updateEaseFactor($userWord, true);

        $this->assertEquals(2.65, $newEase);
    }

    public function test_update_ease_factor_when_not_remembered(): void
    {
        $userWord = new UserWord;
        $userWord->ease_factor = 2.5;

        $newEase = $this->service->updateEaseFactor($userWord, false);

        $this->assertEquals(2.3, $newEase);
    }

    public function test_process_review_updates_counts(): void
    {
        $userWord = new UserWord;
        $userWord->stage = 0;
        $userWord->review_count = 0;
        $userWord->correct_count = 0;

        $this->service->processReview($userWord, true);

        $this->assertEquals(1, $userWord->review_count);
        $this->assertEquals(1, $userWord->correct_count);
        $this->assertNotNull($userWord->next_review_at);
    }

    public function test_process_review_marks_word_as_difficult_after_multiple_failures(): void
    {
        $userWord = new UserWord;
        $userWord->stage = 3;
        $userWord->review_count = 2;
        $userWord->wrong_count = 2;

        $this->service->processReview($userWord, false);

        $this->assertEquals(3, $userWord->review_count);
        $this->assertEquals(3, $userWord->wrong_count);
        $this->assertEquals(2, $userWord->stage);
        $this->assertEquals(3, $userWord->status);
        $this->assertEquals('2026-03-05 12:00:00', $userWord->next_review_at->format('Y-m-d H:i:s'));
    }

    public function test_process_review_marks_word_as_mastered_at_high_stage(): void
    {
        $userWord = new UserWord;
        $userWord->stage = 6;
        $userWord->review_count = 4;
        $userWord->correct_count = 4;

        $this->service->processReview($userWord, true);

        $this->assertEquals(5, $userWord->review_count);
        $this->assertEquals(5, $userWord->correct_count);
        $this->assertEquals(7, $userWord->stage);
        $this->assertEquals(2, $userWord->status);
        $this->assertEquals('2026-08-28 12:00:00', $userWord->next_review_at->format('Y-m-d H:i:s'));
    }
}
