<?php

namespace Tests\Unit\Services\Word;

use App\Models\Word\UserWord;
use App\Services\Word\EbbinghausService;
use Tests\TestCase;

class EbbinghausServiceTest extends TestCase
{
    private EbbinghausService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EbbinghausService;
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
}
