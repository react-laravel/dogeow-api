<?php

namespace App\Services\Note;

use App\Models\Note\Note;
use App\Models\Note\NoteTag;
use Illuminate\Support\Facades\Auth;

class NoteContentService
{
    /**
     * 从编辑器 JSON 中提取纯文本
     */
    public function extractTextFromEditorJson(array $jsonContent): string
    {
        $text = '';

        if (isset($jsonContent['content']) && is_array($jsonContent['content'])) {
            foreach ($jsonContent['content'] as $node) {
                $text .= $this->extractTextFromNode($node);
            }
        }

        return trim($text);
    }

    /**
     * 从单个节点中提取文本
     */
    private function extractTextFromNode(array $node): string
    {
        $text = '';

        if (isset($node['type'])) {
            if ($node['type'] === 'text' && isset($node['text'])) {
                $text .= $node['text'];
            } elseif (isset($node['content']) && is_array($node['content'])) {
                foreach ($node['content'] as $childNode) {
                    $text .= $this->extractTextFromNode($childNode);
                }
                // 在段落后添加换行
                if ($node['type'] === 'paragraph') {
                    $text .= "\n";
                }
            }
        }

        return $text;
    }

    /**
     * 从 content 生成 markdown(优先解析编辑器 JSON)
     */
    public function deriveMarkdownFromContent(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $trimmedContent = trim($content);
        if ($this->isJsonLike($trimmedContent)) {
            $parsedContent = json_decode($trimmedContent, true);
            if (is_array($parsedContent)) {
                return $this->extractTextFromEditorJson($parsedContent);
            }
        }

        return $content;
    }

    /**
     * 简单判断字符串是否可能为 JSON
     */
    private function isJsonLike(string $content): bool
    {
        return str_starts_with($content, '{') || str_starts_with($content, '[');
    }

    /**
     * 处理标签关联
     */
    public function handleTags(Note $note, array $tagNames): void
    {
        $normalized = $this->normalizeTagNames($tagNames);

        if (empty($normalized)) {
            $note->tags()->sync([]);

            return;
        }

        $tagIds = $this->resolveTagIds($normalized, $note->user_id ?? Auth::id());
        $note->tags()->sync($tagIds);
    }

    /**
     * 规范化标签名称
     */
    private function normalizeTagNames(array $tagNames): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $tagNames))));
    }

    /**
     * 根据标签名称获取或创建标签 ID
     */
    private function resolveTagIds(array $tagNames, ?int $userId): array
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            if (empty($tagName)) {
                continue;
            }

            $tag = NoteTag::firstOrCreate(
                [
                    'name' => $tagName,
                    'user_id' => $userId,
                ],
                [
                    'color' => '#3b82f6', // 默认蓝色
                ]
            );

            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }
}
