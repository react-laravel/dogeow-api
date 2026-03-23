<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Resources\Word\BookResource;
use App\Http\Resources\Word\WordResource;
use App\Models\Word\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookController extends Controller
{
    /**
     * 获取单词书列表
     */
    public function index(): AnonymousResourceCollection
    {
        $books = Book::with('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return BookResource::collection($books);
    }

    /**
     * 获取单词书详情
     */
    public function show(int $id): BookResource
    {
        $book = Book::with('category')->findOrFail($id);

        return new BookResource($book);
    }

    /**
     * 获取单词书中的单词列表
     * 支持筛选：all=全部, mastered=已掌握, difficult=困难词, simple=简单词
     * 支持 keyword：按单词内容模糊搜索
     */
    public function words(Request $request, int $id): AnonymousResourceCollection
    {
        $book = Book::findOrFail($id);
        $userId = $this->getCurrentUserId();
        $filter = $request->input('filter', 'all');
        $perPage = $request->input('per_page', 20);
        $keyword = $request->input('keyword');

        $query = $book->words()
            ->with('educationLevels')
            ->orderBy('word_book_word.sort_order')
            ->orderBy('words.id');

        if ($keyword !== null && $keyword !== '') {
            $query->where('words.content', 'like', '%' . trim($keyword) . '%');
        }

        // 根据筛选条件联表查询用户单词状态
        if ($filter === 'mastered') {
            // 已掌握：status = 2
            $query->whereExists(function ($q) use ($userId, $id) {
                $q->selectRaw('1')
                    ->from('user_words')
                    ->whereColumn('user_words.word_id', 'words.id')
                    ->where('user_words.user_id', $userId)
                    ->where('user_words.word_book_id', $id)
                    ->where('user_words.status', 2);
            });
        } elseif ($filter === 'difficult') {
            // 困难词：status = 3
            $query->whereExists(function ($q) use ($userId, $id) {
                $q->selectRaw('1')
                    ->from('user_words')
                    ->whereColumn('user_words.word_id', 'words.id')
                    ->where('user_words.user_id', $userId)
                    ->where('user_words.word_book_id', $id)
                    ->where('user_words.status', 3);
            });
        } elseif ($filter === 'simple') {
            // 简单词：status = 4
            $query->whereExists(function ($q) use ($userId, $id) {
                $q->selectRaw('1')
                    ->from('user_words')
                    ->whereColumn('user_words.word_id', 'words.id')
                    ->where('user_words.user_id', $userId)
                    ->where('user_words.word_book_id', $id)
                    ->where('user_words.status', 4);
            });
        }

        $words = $query->paginate($perPage);

        return WordResource::collection($words);
    }
}
