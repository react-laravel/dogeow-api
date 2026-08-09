<?php

namespace App\Http\Controllers\Api\AiTranslate;

use App\Http\Controllers\Controller;
use App\Models\AiTranslateWordSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class WordSyncController extends Controller
{
    private const MAX_WORDS = 20000;

    public function show(Request $request): JsonResponse
    {
        $record = AiTranslateWordSync::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return $this->success($this->payload($record), 'AI Translate words loaded');
    }

    /**
     * 双向合并同步：以单词为单位，取活动时间更新的一侧。
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'known' => ['nullable', 'array'],
            'studying' => ['nullable', 'array'],
            'client_revision' => ['nullable', 'integer', 'min:0'],
        ]);

        $localKnown = is_array($validated['known'] ?? null) ? $validated['known'] : [];
        $localStudying = is_array($validated['studying'] ?? null) ? $validated['studying'] : [];

        $this->assertWordBudget($localKnown, $localStudying);

        $record = AiTranslateWordSync::query()->firstOrNew([
            'user_id' => $request->user()->id,
        ]);

        $remoteKnown = is_array($record->known) ? $record->known : [];
        $remoteStudying = is_array($record->studying) ? $record->studying : [];

        $merged = $this->mergeCollections(
            knownLocal: $localKnown,
            studyingLocal: $localStudying,
            knownRemote: $remoteKnown,
            studyingRemote: $remoteStudying,
        );

        $this->assertWordBudget($merged['known'], $merged['studying']);

        $record->known = $merged['known'];
        $record->studying = $merged['studying'];
        $record->revision = max(1, (int) $record->revision) + 1;
        $record->synced_at = Carbon::now();
        $record->save();

        return $this->success($this->payload($record), 'AI Translate words synced');
    }

    /**
     * 全量覆盖（用于「云端优先 / 本地优先」显式操作）。
     */
    public function replace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'known' => ['nullable', 'array'],
            'studying' => ['nullable', 'array'],
        ]);

        $known = is_array($validated['known'] ?? null) ? $validated['known'] : [];
        $studying = is_array($validated['studying'] ?? null) ? $validated['studying'] : [];
        $this->assertWordBudget($known, $studying);

        $record = AiTranslateWordSync::query()->firstOrNew([
            'user_id' => $request->user()->id,
        ]);
        $record->known = $this->sanitizeKnown($known);
        $record->studying = $this->sanitizeStudying($studying);
        $record->revision = max(1, (int) $record->revision) + 1;
        $record->synced_at = Carbon::now();
        $record->save();

        return $this->success($this->payload($record), 'AI Translate words replaced');
    }

    /** @return array{known:array<string,mixed>,studying:array<string,mixed>,revision:int,synced_at:string|null} */
    private function payload(?AiTranslateWordSync $record): array
    {
        return [
            'known' => is_array($record?->known) ? $record->known : [],
            'studying' => is_array($record?->studying) ? $record->studying : [],
            'revision' => $record === null ? 0 : (int) $record->revision,
            'synced_at' => $record?->synced_at?->toIso8601String(),
        ];
    }

    /** @param array<string,mixed> $known @param array<string,mixed> $studying */
    private function assertWordBudget(array $known, array $studying): void
    {
        if (count($known) + count($studying) > self::MAX_WORDS) {
            throw ValidationException::withMessages([
                'words' => ['单词数量超过上限（最多 ' . self::MAX_WORDS . ' 个）。'],
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $knownLocal
     * @param  array<string,mixed>  $studyingLocal
     * @param  array<string,mixed>  $knownRemote
     * @param  array<string,mixed>  $studyingRemote
     * @return array{known:array<string,array<string,mixed>>,studying:array<string,array<string,mixed>>}
     */
    private function mergeCollections(
        array $knownLocal,
        array $studyingLocal,
        array $knownRemote,
        array $studyingRemote,
    ): array {
        $words = array_unique(array_merge(
            array_keys($knownLocal),
            array_keys($studyingLocal),
            array_keys($knownRemote),
            array_keys($studyingRemote),
        ));

        $known = [];
        $studying = [];

        foreach ($words as $rawWord) {
            $word = $this->normalizeWord((string) $rawWord);
            if ($word === '') {
                continue;
            }

            $candidates = [];
            if (isset($knownLocal[$rawWord]) || isset($knownLocal[$word])) {
                $candidates[] = ['status' => 'known', 'entry' => $knownLocal[$rawWord] ?? $knownLocal[$word]];
            }
            if (isset($studyingLocal[$rawWord]) || isset($studyingLocal[$word])) {
                $candidates[] = ['status' => 'studying', 'entry' => $studyingLocal[$rawWord] ?? $studyingLocal[$word]];
            }
            if (isset($knownRemote[$rawWord]) || isset($knownRemote[$word])) {
                $candidates[] = ['status' => 'known', 'entry' => $knownRemote[$rawWord] ?? $knownRemote[$word]];
            }
            if (isset($studyingRemote[$rawWord]) || isset($studyingRemote[$word])) {
                $candidates[] = ['status' => 'studying', 'entry' => $studyingRemote[$rawWord] ?? $studyingRemote[$word]];
            }

            usort($candidates, function (array $a, array $b): int {
                return $this->entryActivity($b['entry']) <=> $this->entryActivity($a['entry']);
            });

            $winner = $candidates[0] ?? null;
            if (! $winner) {
                continue;
            }

            if ($winner['status'] === 'known') {
                $known[$word] = $this->sanitizeKnownEntry($winner['entry']);
            } else {
                $studying[$word] = $this->sanitizeStudyingEntry($winner['entry']);
            }
        }

        ksort($known);
        ksort($studying);

        return compact('known', 'studying');
    }

    /** @param array<string,mixed> $known @return array<string,array{addedAt:int}> */
    private function sanitizeKnown(array $known): array
    {
        $result = [];
        foreach ($known as $rawWord => $entry) {
            $word = $this->normalizeWord((string) $rawWord);
            if ($word === '') {
                continue;
            }
            $result[$word] = $this->sanitizeKnownEntry($entry);
        }
        ksort($result);

        return $result;
    }

    /** @param array<string,mixed> $studying @return array<string,array<string,mixed>> */
    private function sanitizeStudying(array $studying): array
    {
        $result = [];
        foreach ($studying as $rawWord => $entry) {
            $word = $this->normalizeWord((string) $rawWord);
            if ($word === '') {
                continue;
            }
            $result[$word] = $this->sanitizeStudyingEntry($entry);
        }
        ksort($result);

        return $result;
    }

    /** @return array{addedAt:int} */
    private function sanitizeKnownEntry(mixed $entry): array
    {
        $addedAt = $this->timestamp($entry['addedAt'] ?? null) ?? (int) (microtime(true) * 1000);

        return ['addedAt' => $addedAt];
    }

    /** @return array<string,mixed> */
    private function sanitizeStudyingEntry(mixed $entry): array
    {
        $entry = is_array($entry) ? $entry : [];
        $level = (int) ($entry['level'] ?? -1);
        $level = max(-1, min(5, $level));
        $history = [];
        if (is_array($entry['history'] ?? null)) {
            foreach (array_slice($entry['history'], -20) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $action = $item['action'] ?? null;
                $at = $this->timestamp($item['at'] ?? null);
                if (($action === 'remember' || $action === 'forget') && $at) {
                    $history[] = ['at' => $at, 'action' => $action];
                }
            }
        }

        return [
            'addedAt' => $this->timestamp($entry['addedAt'] ?? null) ?? (int) (microtime(true) * 1000),
            'level' => $level,
            'lastReviewedAt' => $this->timestamp($entry['lastReviewedAt'] ?? null),
            'nextReviewAt' => $this->timestamp($entry['nextReviewAt'] ?? null),
            'lastAction' => in_array($entry['lastAction'] ?? null, ['remember', 'forget'], true)
                ? $entry['lastAction']
                : null,
            'history' => $history,
        ];
    }

    private function entryActivity(mixed $entry): int
    {
        if (! is_array($entry)) {
            return 0;
        }
        $candidates = [
            $this->timestamp($entry['lastReviewedAt'] ?? null),
            $this->timestamp($entry['updatedAt'] ?? null),
            $this->timestamp($entry['addedAt'] ?? null),
        ];
        if (is_array($entry['history'] ?? null)) {
            foreach ($entry['history'] as $item) {
                $candidates[] = $this->timestamp(is_array($item) ? ($item['at'] ?? null) : null);
            }
        }

        return max(array_map(static fn ($v) => (int) ($v ?? 0), $candidates));
    }

    private function timestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    private function normalizeWord(string $raw): string
    {
        $text = strtolower(trim($raw));
        if ($text === '' || ! preg_match("/^[a-z][a-z'\\-]*$/i", $text)) {
            return '';
        }
        if (strlen($text) < 2 && $text !== 'a' && $text !== 'i') {
            return '';
        }

        return $text;
    }
}
