<?php

namespace App\Http\Controllers\Api\Book;

use App\Http\Controllers\Controller;
use App\Models\Book\BookMark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookMarkController extends Controller
{
    public function index(Request $request, string $book): JsonResponse
    {
        $marks = BookMark::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book)
            ->orderByDesc('created_at_ms')
            ->get()
            ->map(fn (BookMark $mark) => $this->toPayload($mark))
            ->values();

        return response()->json($marks);
    }

    public function store(Request $request, string $book): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::in(['position', 'collection'])],
            'chapterId' => ['required', 'integer', 'min:1'],
            'chapterTitle' => ['required', 'string', 'max:255'],
            'scrollTop' => ['required', 'numeric', 'min:0'],
            'pairIndex' => ['nullable', 'integer', 'min:0'],
            'excerpt' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:4000'],
            'createdAt' => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) $request->user()->id;
        $positionKey = $this->positionKey($data);

        if ($data['kind'] === 'position') {
            $duplicate = BookMark::query()
                ->where('user_id', $userId)
                ->where('book_id', $book)
                ->where('kind', 'position')
                ->where('position_key', $positionKey)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'mark' => $this->toPayload($duplicate),
                    'created' => false,
                ]);
            }
        }

        $mark = BookMark::create([
            'id' => $data['id'],
            'user_id' => $userId,
            'book_id' => $book,
            'kind' => $data['kind'],
            'chapter_id' => $data['chapterId'],
            'chapter_title' => $data['chapterTitle'],
            'scroll_top' => $data['scrollTop'],
            'pair_index' => $data['pairIndex'] ?? null,
            'position_key' => $positionKey,
            'excerpt' => trim((string) ($data['excerpt'] ?? '')),
            'note' => trim((string) ($data['note'] ?? '')),
            'created_at_ms' => $data['createdAt'],
        ]);

        return response()->json([
            'mark' => $this->toPayload($mark),
            'created' => true,
        ], 201);
    }

    public function destroy(Request $request, string $book, string $mark): JsonResponse
    {
        BookMark::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book)
            ->where('id', $mark)
            ->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function positionKey(array $data): ?string
    {
        if (($data['kind'] ?? null) !== 'position') {
            return null;
        }

        $chapterId = (int) $data['chapterId'];
        if (array_key_exists('pairIndex', $data) && $data['pairIndex'] !== null) {
            return "chapter:{$chapterId}:pair:" . (int) $data['pairIndex'];
        }

        return "chapter:{$chapterId}:scroll:" . round((float) $data['scrollTop']);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(BookMark $mark): array
    {
        return [
            'id' => $mark->id,
            'bookId' => $mark->book_id,
            'kind' => $mark->kind,
            'chapterId' => $mark->chapter_id,
            'chapterTitle' => $mark->chapter_title,
            'scrollTop' => $mark->scroll_top,
            'pairIndex' => $mark->pair_index,
            'excerpt' => $mark->excerpt ?? '',
            'note' => $mark->note ?? '',
            'createdAt' => $mark->created_at_ms,
        ];
    }
}
