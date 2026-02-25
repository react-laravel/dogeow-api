<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatModerationAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ContentFilterService
{
    /**
     * 不当词汇列表（基础实现）
     * 生产环境应存储在数据库或外部服务中
     */
    private const INAPPROPRIATE_WORDS = [
        'stupid',
        'spam',
        'hate',
        'violence',
    ];

    /**
     * 敏感词替换内容
     */
    private const WORD_REPLACEMENTS = [
        'spam' => '****',
        'stupid' => '[filtered]',
        'hate' => '[filtered]',
        'violence' => '[filtered]',
    ];

    /**
     * 垃圾信息检测阈值
     */
    private const SPAM_MESSAGE_LIMIT = 5; // 每分钟消息数上限

    private const SPAM_DUPLICATE_LIMIT = 3; // 重复消息上限

    private const SPAM_CAPS_THRESHOLD = 0.7; // 大写字母比例70%

    private const SPAM_REPETITION_THRESHOLD = 0.5; // 重复字符比例50%

    /**
     * 检查消息是否包含不当内容
     */
    public function checkInappropriateContent(string $message): array
    {
        $violations = [];
        $severity = 'low';
        $filteredMessage = $message;
        $lowerMessage = strtolower($message);

        foreach (self::INAPPROPRIATE_WORDS as $word) {
            $wordLower = strtolower($word);
            if (strpos($lowerMessage, $wordLower) !== false) {
                $wordSeverity = $this->getWordSeverity($word);
                $violations[] = [
                    'type' => 'inappropriate_word',
                    'word' => $word,
                    'severity' => $wordSeverity,
                ];

                // 如果有替换内容则进行替换
                if (array_key_exists($word, self::WORD_REPLACEMENTS)) {
                    $filteredMessage = str_ireplace($word, self::WORD_REPLACEMENTS[$word], $filteredMessage);
                }

                // 更新整体严重等级
                if ($wordSeverity === 'high' || ($wordSeverity === 'medium' && $severity === 'low')) {
                    $severity = $wordSeverity;
                }
            }
        }

        return [
            'has_violations' => ! empty($violations),
            'violations' => $violations,
            'severity' => $severity,
            'filtered_message' => $filteredMessage,
            'action_required' => $severity === 'high' || count($violations) >= 3,
        ];
    }

    /**
     * 获取指定词汇的严重等级
     */
    private function getWordSeverity(string $word): string
    {
        static $highSeverityWords = ['hate', 'violence'];
        static $mediumSeverityWords = [];

        if (in_array($word, $highSeverityWords, true)) {
            return 'high';
        }
        if (in_array($word, $mediumSeverityWords, true)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * 检测消息中的垃圾信息模式
     */
    public function detectSpam(string $message, int $userId, int $roomId): array
    {
        $violations = [];
        $severity = 'low';

        // 检查消息频率
        $frequencyCheck = $this->checkMessageFrequency($userId, $roomId);
        if ($frequencyCheck['is_spam']) {
            $violations[] = [
                'type' => 'high_frequency',
                'details' => $frequencyCheck,
                'severity' => 'high',
            ];
            $severity = 'high';
        }

        // 检查重复消息
        $duplicateCheck = $this->checkDuplicateMessages($message, $userId, $roomId);
        if ($duplicateCheck['is_spam']) {
            $violations[] = [
                'type' => 'duplicate_message',
                'details' => $duplicateCheck,
                'severity' => 'medium',
            ];
            if ($severity === 'low') {
                $severity = 'medium';
            }
        }

        // 检查过多大写字母
        $capsCheck = $this->checkExcessiveCaps($message);
        if ($capsCheck['is_spam']) {
            $violations[] = [
                'type' => 'excessive_caps',
                'details' => $capsCheck,
                'severity' => 'low',
            ];
        }

        // 检查字符重复
        $repetitionCheck = $this->checkCharacterRepetition($message);
        if ($repetitionCheck['is_spam']) {
            $violations[] = [
                'type' => 'character_repetition',
                'details' => $repetitionCheck,
                'severity' => 'low',
            ];
        }

        // 检查URL垃圾信息
        $urlCheck = $this->checkUrlSpam($message);
        if ($urlCheck['is_spam']) {
            $violations[] = [
                'type' => 'url_spam',
                'details' => $urlCheck,
                'severity' => 'medium',
            ];
            if ($severity === 'low') {
                $severity = 'medium';
            }
        }

        return [
            'is_spam' => ! empty($violations),
            'violations' => $violations,
            'severity' => $severity,
            'action_required' => $severity === 'high' || count($violations) >= 2,
        ];
    }

    /**
     * 检查消息频率用于垃圾信息检测
     */
    private function checkMessageFrequency(int $userId, int $roomId): array
    {
        $cacheKey = "chat_message_frequency_{$userId}_{$roomId}";
        $messages = Cache::get($cacheKey, []);

        // 清理1分钟前的旧消息
        $oneMinuteAgo = now()->subMinute()->timestamp;
        $messages = array_filter($messages, static function ($timestamp) use ($oneMinuteAgo) {
            return $timestamp > $oneMinuteAgo;
        });

        // 添加当前消息时间戳
        $messages[] = now()->timestamp;

        // 存回缓存，有效期5分钟
        Cache::put($cacheKey, $messages, 300); // 5分钟

        $messageCount = count($messages);

        return [
            'is_spam' => $messageCount > self::SPAM_MESSAGE_LIMIT,
            'message_count' => $messageCount,
            'limit' => self::SPAM_MESSAGE_LIMIT,
            'time_window' => '1 minute',
        ];
    }

    /**
     * 检查重复消息
     */
    private function checkDuplicateMessages(string $message, int $userId, int $roomId): array
    {
        $recentMessages = ChatMessage::where('user_id', $userId)
            ->where('room_id', $roomId)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->pluck('message')
            ->toArray();

        $messageHash = md5(strtolower(trim($message)));
        $duplicateCount = count(array_filter($recentMessages, static function ($recentMessage) use ($messageHash) {
            return md5(strtolower(trim($recentMessage))) === $messageHash;
        }));

        return [
            'is_spam' => $duplicateCount >= self::SPAM_DUPLICATE_LIMIT,
            'duplicate_count' => $duplicateCount,
            'limit' => self::SPAM_DUPLICATE_LIMIT,
            'time_window' => '5 minutes',
        ];
    }

    /**
     * 检查是否有过多大写字母
     */
    private function checkExcessiveCaps(string $message): array
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $message);
        $totalChars = strlen($letters);

        if ($totalChars < 10) {
            return ['is_spam' => false]; // 字符太短无法判断
        }

        $capsChars = strlen(preg_replace('/[^A-Z]/', '', $letters));
        $capsRatio = $capsChars / $totalChars;

        return [
            'is_spam' => $capsRatio > self::SPAM_CAPS_THRESHOLD,
            'caps_ratio' => $capsRatio,
            'threshold' => self::SPAM_CAPS_THRESHOLD,
            'caps_count' => $capsChars,
            'total_letters' => $totalChars,
        ];
    }

    /**
     * 检查字符重复的垃圾信息
     */
    private function checkCharacterRepetition(string $message): array
    {
        $length = strlen($message);
        if ($length < 10) {
            return ['is_spam' => false];
        }

        $repetitionCount = 0;
        $i = 0;
        while ($i < $length) {
            $char = $message[$i];
            $j = $i + 1;
            while ($j < $length && $message[$j] === $char) {
                $j++;
            }
            $consecutiveCount = $j - $i;
            if ($consecutiveCount >= 4) { // 4个及以上连续相同字符
                $repetitionCount += $consecutiveCount;
            }
            $i = $j;
        }

        $repetitionRatio = $repetitionCount / $length;

        return [
            'is_spam' => $repetitionRatio > self::SPAM_REPETITION_THRESHOLD,
            'repetition_ratio' => $repetitionRatio,
            'threshold' => self::SPAM_REPETITION_THRESHOLD,
            'repetition_count' => $repetitionCount,
            'total_chars' => $length,
        ];
    }

    /**
     * 检查URL垃圾信息
     */
    private function checkUrlSpam(string $message): array
    {
        // 统计消息中的URL数量
        $urlPattern = '/https?:\/\/[^\s]+/i';
        preg_match_all($urlPattern, $message, $matches);
        $urlCount = count($matches[0]);

        // 检查可疑URL模式
        $suspiciousPatterns = [
            '/bit\.ly/i',
            '/tinyurl/i',
            '/t\.co/i',
            '/goo\.gl/i',
            '/ow\.ly/i',
            '/free.*money/i',
            '/click.*here/i',
            '/limited.*time/i',
        ];

        $suspiciousUrls = 0;
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                $suspiciousUrls++;
            }
        }

        return [
            'is_spam' => $urlCount > 2 || $suspiciousUrls > 0,
            'url_count' => $urlCount,
            'suspicious_urls' => $suspiciousUrls,
            'urls' => $matches[0],
        ];
    }

    /**
     * 处理消息内容过滤
     */
    public function processMessage(string $message, int $userId, int $roomId): array
    {
        $result = [
            'allowed' => true,
            'filtered_message' => $message,
            'violations' => [],
            'actions_taken' => [],
            'severity' => 'none',
        ];

        // 检查不当内容
        $contentCheck = $this->checkInappropriateContent($message);
        if ($contentCheck['has_violations']) {
            $result['violations']['content'] = $contentCheck;
            $result['filtered_message'] = $contentCheck['filtered_message'];
            $result['severity'] = $contentCheck['severity'];

            if ($contentCheck['action_required']) {
                $result['allowed'] = false;
                $result['actions_taken'][] = 'message_blocked';

                // 记录操作日志
                $this->logModerationAction($roomId, $userId, null, ChatModerationAction::ACTION_CONTENT_FILTER, [
                    'original_message' => $message,
                    'violations' => $contentCheck['violations'],
                    'severity' => $contentCheck['severity'],
                ]);
            }
        }

        // 检查垃圾信息
        $spamCheck = $this->detectSpam($message, $userId, $roomId);
        if ($spamCheck['is_spam']) {
            $result['violations']['spam'] = $spamCheck;

            if ($spamCheck['action_required']) {
                $result['allowed'] = false;
                $result['actions_taken'][] = 'spam_blocked';

                // 记录操作日志
                $this->logModerationAction($roomId, $userId, null, ChatModerationAction::ACTION_SPAM_DETECTION, [
                    'message' => $message,
                    'violations' => $spamCheck['violations'],
                    'severity' => $spamCheck['severity'],
                ]);

                // 如果垃圾信息严重则自动禁言用户
                if ($spamCheck['severity'] === 'high') {
                    $this->autoMuteUser($userId, $roomId, 'Automatic mute for spam detection');
                    $result['actions_taken'][] = 'user_auto_muted';
                }
            }

            // 如果垃圾信息严重等级更高则更新
            if ($spamCheck['severity'] === 'high' || ($spamCheck['severity'] === 'medium' && $result['severity'] === 'low')) {
                $result['severity'] = $spamCheck['severity'];
            }
        }

        return $result;
    }

    /**
     * 用户违规自动禁言
     */
    private function autoMuteUser(int $userId, int $roomId, string $reason, int $durationMinutes = 10): bool
    {
        try {
            $roomUser = \App\Models\Chat\ChatRoomUser::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();

            if ($roomUser) {
                $roomUser->update([
                    'is_muted' => true,
                    'muted_until' => now()->addMinutes($durationMinutes),
                    'muted_by' => 1, // 系统用户
                ]);

                // 记录自动禁言操作
                $this->logModerationAction($roomId, $userId, 1, ChatModerationAction::ACTION_MUTE_USER, [
                    'duration_minutes' => $durationMinutes,
                    'auto_action' => true,
                    'reason' => $reason,
                ]);

                return true;
            }
        } catch (\Exception $e) {
            Log::error('自动禁言用户失败', [
                'user_id' => $userId,
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * 记录内容审核操作
     */
    private function logModerationAction(int $roomId, int $targetUserId, ?int $moderatorId, string $actionType, array $metadata = []): void
    {
        try {
            ChatModerationAction::create([
                'room_id' => $roomId,
                'moderator_id' => $moderatorId ?? 1, // 自动操作使用系统用户
                'target_user_id' => $targetUserId,
                'action_type' => $actionType,
                'reason' => $metadata['reason'] ?? 'Automated content filtering',
                'metadata' => $metadata,
            ]);
        } catch (\Exception $e) {
            Log::error('记录内容审核操作失败', [
                'room_id' => $roomId,
                'target_user_id' => $targetUserId,
                'action_type' => $actionType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取内容过滤统计信息
     */
    public function getFilterStats(?int $roomId = null, int $days = 7): array
    {
        $query = ChatModerationAction::where('created_at', '>=', now()->subDays($days))
            ->whereIn('action_type', [
                ChatModerationAction::ACTION_CONTENT_FILTER,
                ChatModerationAction::ACTION_SPAM_DETECTION,
            ]);

        if ($roomId) {
            $query->where('room_id', $roomId);
        }

        $actions = $query->get();

        $stats = [
            'total_actions' => $actions->count(),
            'content_filter_actions' => $actions->where('action_type', ChatModerationAction::ACTION_CONTENT_FILTER)->count(),
            'spam_detection_actions' => $actions->where('action_type', ChatModerationAction::ACTION_SPAM_DETECTION)->count(),
            'severity_breakdown' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0,
            ],
            'top_violations' => [],
            'affected_users' => $actions->pluck('target_user_id')->unique()->count(),
            'period_days' => $days,
        ];

        // 统计严重等级分布
        foreach ($actions as $action) {
            $severity = $action->metadata['severity'] ?? 'low';
            if (isset($stats['severity_breakdown'][$severity])) {
                $stats['severity_breakdown'][$severity]++;
            }
        }

        // 获取违规类型排行
        $violationTypes = [];
        foreach ($actions as $action) {
            if (isset($action->metadata['violations'])) {
                foreach ($action->metadata['violations'] as $violation) {
                    $type = $violation['type'] ?? 'unknown';
                    $violationTypes[$type] = ($violationTypes[$type] ?? 0) + 1;
                }
            }
        }

        arsort($violationTypes);
        $stats['top_violations'] = array_slice($violationTypes, 0, 10, true);

        return $stats;
    }
}
