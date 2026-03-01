<?php

namespace Tests\Unit\Utils;

use App\Utils\CharLengthHelper;
use PHPUnit\Framework\TestCase;

class CharLengthHelperTest extends TestCase
{
    /**
     * Test calculateCharLength with empty string
     */
    public function test_calculate_char_length_with_empty_string(): void
    {
        $this->assertEquals(0, CharLengthHelper::calculateCharLength(''));
    }

    /**
     * Test calculateCharLength with only English characters
     */
    public function test_calculate_char_length_with_english(): void
    {
        $this->assertEquals(5, CharLengthHelper::calculateCharLength('hello'));
        $this->assertEquals(11, CharLengthHelper::calculateCharLength('Hello World'));
    }

    /**
     * Test calculateCharLength with numbers
     */
    public function test_calculate_char_length_with_numbers(): void
    {
        $this->assertEquals(3, CharLengthHelper::calculateCharLength('123'));
        $this->assertEquals(5, CharLengthHelper::calculateCharLength('12345'));
    }

    /**
     * Test calculateCharLength with Chinese characters
     */
    public function test_calculate_char_length_with_chinese(): void
    {
        // 每个中文字符算 2
        $this->assertEquals(2, CharLengthHelper::calculateCharLength('你'));
        $this->assertEquals(4, CharLengthHelper::calculateCharLength('你好'));
        $this->assertEquals(6, CharLengthHelper::calculateCharLength('你好吗'));
    }

    /**
     * Test calculateCharLength with mixed English and Chinese
     */
    public function test_calculate_char_length_with_mixed_english_chinese(): void
    {
        // hello (5) + 你好 (4) = 9
        $this->assertEquals(9, CharLengthHelper::calculateCharLength('hello你好'));
        // hi (2) + 中国 (4) = 6
        $this->assertEquals(6, CharLengthHelper::calculateCharLength('hi中国'));
    }

    /**
     * Test calculateCharLength with simple emoji (single code point)
     */
    public function test_calculate_char_length_with_simple_emoji(): void
    {
        // 每个 emoji 算 2
        $this->assertEquals(2, CharLengthHelper::calculateCharLength('😀'));
        $this->assertEquals(4, CharLengthHelper::calculateCharLength('😀😁'));
    }

    /**
     * Test calculateCharLength with misc symbols
     */
    public function test_calculate_char_length_with_misc_symbols(): void
    {
        // ☀ (misc symbols) 算 2
        $this->assertEquals(2, CharLengthHelper::calculateCharLength('☀'));
        // ★ (dingbats) 算 2
        $this->assertEquals(2, CharLengthHelper::calculateCharLength('★'));
    }

    /**
     * Test calculateCharLength with flag emoji (regional indicator symbols)
     * This tests getEmojiLength for flags which are 2 code points
     */
    public function test_calculate_char_length_with_flag_emoji(): void
    {
        // 国旗 emoji 由两个区域指示符号组成
        // 🇨🇳 (CN) 算 2 个字符长度
        $this->assertEquals(2, CharLengthHelper::calculateCharLength('🇨🇳'));
        // 🇺🇸 (US) 算 2 个字符长度
        $this->assertEquals(2, CharLengthHelper::calculateCharLength('🇺🇸'));
    }

    /**
     * Test calculateCharLength with mixed content
     */
    public function test_calculate_char_length_with_mixed_content(): void
    {
        // hello (5) + 😀 (2) + 你好 (4) = 11
        $this->assertEquals(11, CharLengthHelper::calculateCharLength('hello😀你好'));
        // hi (2) + 🇨🇳 (2) + 中国 (4) = 8
        $this->assertEquals(8, CharLengthHelper::calculateCharLength('hi🇨🇳中国'));
    }

    /**
     * Test exceedsMaxLength method
     */
    public function test_exceeds_max_length(): void
    {
        $this->assertFalse(CharLengthHelper::exceedsMaxLength('hello', 10));
        $this->assertTrue(CharLengthHelper::exceedsMaxLength('hello', 3));
        $this->assertFalse(CharLengthHelper::exceedsMaxLength('你好', 10));
        $this->assertTrue(CharLengthHelper::exceedsMaxLength('你好', 3));
    }

    /**
     * Test belowMinLength method
     */
    public function test_below_min_length(): void
    {
        $this->assertFalse(CharLengthHelper::belowMinLength('hello', 3));
        $this->assertTrue(CharLengthHelper::belowMinLength('hello', 10));
        $this->assertFalse(CharLengthHelper::belowMinLength('你好', 3));
        $this->assertTrue(CharLengthHelper::belowMinLength('你好', 10));
    }

    /**
     * Test with special characters
     */
    public function test_calculate_char_length_with_special_characters(): void
    {
        // 空格算 1
        $this->assertEquals(6, CharLengthHelper::calculateCharLength('hello '));
        // 标点符号算 1
        $this->assertEquals(6, CharLengthHelper::calculateCharLength('hello!'));
        // 混合: 你好(4) + ,(1) + 空格(1) + world(5) + !(1) = 12
        $this->assertEquals(12, CharLengthHelper::calculateCharLength('你好, world!'));
    }

    /**
     * Test exceedsMaxLength at boundary
     */
    public function test_exceeds_max_length_at_boundary(): void
    {
        $this->assertFalse(CharLengthHelper::exceedsMaxLength('hello', 5));
        $this->assertFalse(CharLengthHelper::exceedsMaxLength('你好', 4));
        $this->assertTrue(CharLengthHelper::exceedsMaxLength('hello', 4));
    }

    /**
     * Test belowMinLength at boundary
     */
    public function test_below_min_length_at_boundary(): void
    {
        $this->assertFalse(CharLengthHelper::belowMinLength('hello', 5));
        $this->assertFalse(CharLengthHelper::belowMinLength('你好', 4));
        $this->assertTrue(CharLengthHelper::belowMinLength('hi', 5));
    }
}
