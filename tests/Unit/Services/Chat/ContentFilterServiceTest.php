<?php

namespace Tests\Unit\Services\Chat;

use App\Services\Chat\ContentFilterService;
use Tests\TestCase;

class ContentFilterServiceTest extends TestCase
{
    private ContentFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentFilterService;
    }

    public function test_check_inappropriate_content_returns_clean_for_normal_message(): void
    {
        $result = $this->service->checkInappropriateContent('Hello world');

        $this->assertFalse($result['has_violations']);
        $this->assertEquals('low', $result['severity']);
    }

    public function test_check_inappropriate_content_detects_inappropriate_words(): void
    {
        $result = $this->service->checkInappropriateContent('This is spam message');

        $this->assertTrue($result['has_violations']);
        $this->assertNotEmpty($result['violations']);
    }

    public function test_check_inappropriate_content_filters_message(): void
    {
        $result = $this->service->checkInappropriateContent('This is spam message');

        $this->assertStringContainsString('****', $result['filtered_message']);
    }

    public function test_check_inappropriate_content_returns_high_severity_for_hate_words(): void
    {
        $result = $this->service->checkInappropriateContent('I hate you');

        $this->assertTrue($result['has_violations']);
        $this->assertEquals('high', $result['severity']);
    }

    public function test_detect_spam_returns_clean_for_normal_message(): void
    {
        $result = $this->service->detectSpam('Hello world', 1, 1);

        $this->assertFalse($result['is_spam']);
    }

    public function test_detect_spam_detects_excessive_caps(): void
    {
        $result = $this->service->detectSpam('THIS IS A TEST MESSAGE WITH ALL CAPS', 1, 1);

        $this->assertNotEmpty($result['violations']);
    }

    public function test_detect_spam_detects_character_repetition(): void
    {
        $result = $this->service->detectSpam('helloooooooooooo world', 1, 1);

        $this->assertNotEmpty($result['violations']);
    }

    public function test_detect_spam_detects_url_spam(): void
    {
        $result = $this->service->detectSpam('Check out https://example.com click here for free money', 1, 1);

        $this->assertNotEmpty($result['violations']);
    }

    public function test_process_message_returns_allowed_for_clean_message(): void
    {
        $result = $this->service->processMessage('Hello world', 1, 1);

        $this->assertTrue($result['allowed']);
    }

    public function test_process_message_blocks_high_severity_content(): void
    {
        $result = $this->service->processMessage('I hate violence', 1, 1);

        $this->assertFalse($result['allowed']);
    }
}
