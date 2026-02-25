<?php

namespace App\Utils;

class CharLengthHelper
{
    /**
     * 计算文本的字符长度（中文/emoji算2，数字/字母算1）
     *
     * @param  string  $text  要计算的文本
     * @return int 字符长度（整数）
     */
    public static function calculateCharLength(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        $length = 0;
        $i = 0;
        $textLength = mb_strlen($text, 'UTF-8');

        while ($i < $textLength) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $codePoint = self::getCodePoint($char);

            // 检查是否是中文字符（包括 CJK 统一表意文字）
            if (
                ($codePoint >= 0x4E00 && $codePoint <= 0x9FFF) || // CJK 统一表意文字
                ($codePoint >= 0x3400 && $codePoint <= 0x4DBF) || // CJK 扩展 A
                ($codePoint >= 0xF900 && $codePoint <= 0xFAFF) // CJK 兼容表意文字
            ) {
                $length += 2;
                $i += 1;
            }
            // 检查是否是 emoji
            elseif (
                ($codePoint >= 0x1F300 && $codePoint <= 0x1F9FF) || // 杂项符号、象形文字、表情符号
                ($codePoint >= 0x2600 && $codePoint <= 0x26FF) || // 杂项符号
                ($codePoint >= 0x2700 && $codePoint <= 0x27BF) || // 装饰符号
                ($codePoint >= 0x1F1E6 && $codePoint <= 0x1F1FF) // 区域指示符号（国旗）
            ) {
                $length += 2;
                // emoji 可能是多个代码点组成的，需要检查
                $emojiLength = self::getEmojiLength($text, $i);
                $i += $emojiLength;
            }
            // 数字和字母算 1 个字符
            else {
                $length += 1;
                $i += 1;
            }
        }

        return $length;
    }

    /**
     * 获取字符的代码点
     */
    private static function getCodePoint(string $char): int
    {
        $code = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));

        return $code[1] ?? 0;
    }

    /**
     * 获取 emoji 的长度（可能是多个代码点）
     */
    private static function getEmojiLength(string $text, int $start): int
    {
        // 简单的实现：检查是否是组合 emoji（如国旗）
        $char = mb_substr($text, $start, 1, 'UTF-8');
        $codePoint = self::getCodePoint($char);

        // 区域指示符号（国旗）通常是两个字符
        if ($codePoint >= 0x1F1E6 && $codePoint <= 0x1F1FF) {
            return 2;
        }

        return 1;
    }

    /**
     * 检查文本是否超过最大字符长度
     *
     * @param  string  $text  要检查的文本
     * @param  int  $maxLength  最大字符长度
     * @return bool 是否超过
     */
    public static function exceedsMaxLength(string $text, int $maxLength): bool
    {
        return self::calculateCharLength($text) > $maxLength;
    }

    /**
     * 检查文本是否少于最小字符长度
     *
     * @param  string  $text  要检查的文本
     * @param  int  $minLength  最小字符长度
     * @return bool 是否少于
     */
    public static function belowMinLength(string $text, int $minLength): bool
    {
        return self::calculateCharLength($text) < $minLength;
    }
}
