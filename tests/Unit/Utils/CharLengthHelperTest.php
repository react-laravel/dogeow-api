<?php

namespace Tests\Unit\Utils;

use Dogeow\PhpHelpers\CharLength;
use PHPUnit\Framework\TestCase;

class CharLengthHelperTest extends TestCase
{
    /**
     * Test calculateCharLength with empty string
     */
    public function test_calculate_char_length_with_empty_string(): void
    {
        $this->assertEquals(0, CharLength::calculate(''));
    }

    /**
     * Test calculateCharLength with only English characters
     */
    public function test_calculate_char_length_with_english(): void
    {
        $this->assertEquals(5, CharLength::calculate('hello'));
        $this->assertEquals(11, CharLength::calculate('Hello World'));
    }

    /**
     * Test calculateCharLength with numbers
     */
    public function test_calculate_char_length_with_numbers(): void
    {
        $this->assertEquals(3, CharLength::calculate('123'));
        $this->assertEquals(5, CharLength::calculate('12345'));
    }

    /**
     * Test calculateCharLength with Chinese characters
     */
    public function test_calculate_char_length_with_chinese(): void
    {
        // 每个中文字符算 2
        $this->assertEquals(2, CharLength::calculate('你'));
        $this->assertEquals(4, CharLength::calculate('你好'));
        $this->assertEquals(6, CharLength::calculate('你好吗'));
    }

    /**
     * Test calculateCharLength with mixed English and Chinese
     */
    public function test_calculate_char_length_with_mixed_english_chinese(): void
    {
        // hello (5) + space (1) + 你好 (4) = 10
        $this->assertEquals(10, CharLength::calculate('hello 你好'));
        // hi (2) + space (1) + 中国 (4) = 7
        $this->assertEquals(7, CharLength::calculate('hi 中国'));
    }

    /**
     * Test calculateCharLength with simple emoji (single code point)
     */
    public function test_calculate_char_length_with_simple_emoji(): void
    {
        // 每个 emoji 算 2
        $this->assertEquals(2, CharLength::calculate('😀'));
        $this->assertEquals(4, CharLength::calculate('😀😁'));
    }

    /**
     * Test calculateCharLength with misc symbols
     */
    public function test_calculate_char_length_with_misc_symbols(): void
    {
        // ☀ (misc symbols) 算 2
        $this->assertEquals(2, CharLength::calculate('☀'));
        // ★ (dingbats) 算 2
        $this->assertEquals(2, CharLength::calculate('★'));
    }

    /**
     * Test calculateCharLength with flag emoji (regional indicator symbols)
     * This tests getEmojiLength for flags which are 2 code points
     */
    public function test_calculate_char_length_with_flag_emoji(): void
    {
        // 国旗 emoji 由两个区域指示符号组成
        // 🇨🇳 (CN) 算 2 个字符长度
        $this->assertEquals(2, CharLength::calculate('🇨🇳'));
        // 🇺🇸 (US) 算 2 个字符长度
        $this->assertEquals(2, CharLength::calculate('🇺🇸'));
    }

    /**
     * Test calculateCharLength with mixed content
     */
    public function test_calculate_char_length_with_mixed_content(): void
    {
        // hello (5) + 😀 (2) + 你好 (4) = 11
        $this->assertEquals(11, CharLength::calculate('hello😀你好'));
        // hi (2) + 🇨🇳 (2) + 中国 (4) = 8
        $this->assertEquals(8, CharLength::calculate('hi🇨🇳中国'));
    }

    /**
     * Test exceedsMaxLength method
     */
    public function test_exceeds_max_length(): void
    {
        $this->assertFalse(CharLength::exceedsMax('hello', 10));
        $this->assertTrue(CharLength::exceedsMax('hello', 3));
        $this->assertFalse(CharLength::exceedsMax('你好', 10));
        $this->assertTrue(CharLength::exceedsMax('你好', 3));
    }

    /**
     * Test belowMinLength method
     */
    public function test_below_min_length(): void
    {
        $this->assertFalse(CharLength::belowMin('hello', 3));
        $this->assertTrue(CharLength::belowMin('hello', 10));
        $this->assertFalse(CharLength::belowMin('你好', 3));
        $this->assertTrue(CharLength::belowMin('你好', 10));
    }

    /**
     * Test with special characters
     */
    public function test_calculate_char_length_with_special_characters(): void
    {
        // 空格算 1
        $this->assertEquals(6, CharLength::calculate('hello '));
        // 标点符号算 1
        $this->assertEquals(6, CharLength::calculate('hello!'));
        // 混合: 你好(4) + ,(1) + 空格(1) + world(5) + !(1) = 12
        $this->assertEquals(12, CharLength::calculate('你好, world!'));
    }

    /**
     * Test exceedsMaxLength at boundary
     */
    public function test_exceeds_max_length_at_boundary(): void
    {
        $this->assertFalse(CharLength::exceedsMax('hello', 5));
        $this->assertFalse(CharLength::exceedsMax('你好', 4));
        $this->assertTrue(CharLength::exceedsMax('hello', 4));
    }

    /**
     * Test belowMinLength at boundary
     */
    public function test_below_min_length_at_boundary(): void
    {
        $this->assertFalse(CharLength::belowMin('hello', 5));
        $this->assertFalse(CharLength::belowMin('你好', 4));
        $this->assertTrue(CharLength::belowMin('hi', 5));
    }
}
