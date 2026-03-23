<?php

namespace Tests\Unit\Utils;

use App\Utils\Constants;
use Tests\TestCase;

class ConstantsTest extends TestCase
{
    public function test_chat_returns_config(): void
    {
        $result = Constants::chat();

        $this->assertIsArray($result);
    }

    public function test_chat_with_section(): void
    {
        $result = Constants::chat('message');

        $this->assertIsArray($result);
    }

    public function test_chat_with_section_and_key(): void
    {
        $result = Constants::chat('message', 'max_length');

        $this->assertIsInt($result);
    }

    public function test_upload_returns_config(): void
    {
        $result = Constants::upload();

        $this->assertIsArray($result);
    }

    public function test_upload_with_key(): void
    {
        $result = Constants::upload('max_file_size');

        $this->assertIsInt($result);
    }

    public function test_image_returns_config(): void
    {
        $result = Constants::image();

        $this->assertIsArray($result);
    }

    public function test_cache_returns_config(): void
    {
        $result = Constants::cache();

        $this->assertIsArray($result);
    }

    public function test_validation_returns_config(): void
    {
        $result = Constants::validation();

        $this->assertIsArray($result);
    }

    public function test_validation_with_section(): void
    {
        // 测试只传入 section 参数
        $result = Constants::validation('user');

        $this->assertIsArray($result);
    }

    public function test_validation_with_section_and_key(): void
    {
        // 测试传入 section 和 key 参数
        $result = Constants::validation('user', 'password_min_length');

        $this->assertIsInt($result);
    }

    public function test_api_returns_config(): void
    {
        $result = Constants::api();

        $this->assertIsArray($result);
    }

    public function test_chat_message_max_length(): void
    {
        $result = Constants::chatMessageMaxLength();

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function test_chat_room_name_max_length(): void
    {
        $result = Constants::chatRoomNameMaxLength();

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function test_max_file_size(): void
    {
        $result = Constants::maxFileSize();

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function test_allowed_extensions(): void
    {
        $result = Constants::allowedExtensions();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function test_thumbnail_size(): void
    {
        $result = Constants::thumbnailSize();

        $this->assertIsInt($result);
    }

    public function test_compressed_max_size(): void
    {
        $result = Constants::compressedMaxSize();

        $this->assertIsInt($result);
    }
}
