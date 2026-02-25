<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Word\CreateWordRequest;
use App\Http\Requests\Word\MarkWordRequest;
use App\Http\Resources\Word\WordResource;
use App\Models\Word\Book;
use App\Models\Word\EducationLevel;
use App\Models\Word\UserSetting;
use App\Models\Word\UserWord;
use App\Models\Word\Word;
use App\Services\Word\EbbinghausService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LearningController extends Controller
{
    public function __construct(
        private readonly EbbinghausService $ebbinghausService
    ) {}

    /**
     * 获取今日学习单词（包含复习词和新词）
     */
    public function getDailyWords(): AnonymousResourceCollection
    {
        $user = Auth::user();
        $setting = $this->getUserSetting($user->id);

        if (! $setting->current_book_id) {
            return WordResource::collection(collect());
        }

        $book = Book::find($setting->current_book_id);
        if (! $book) {
            return WordResource::collection(collect());
        }
        $dailyCount = $setting->daily_new_words;
        $reviewCount = $setting->daily_new_words * $setting->review_multiplier;

        // 1. 先获取需要复习的单词（优先，限制在当前单词书）
        $reviewUserWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $book->id)
            ->whereNotIn('status', [0, 4]) // 已学习且非简单词
            ->where('next_review_at', '<=', now())
            ->with(['word.educationLevels'])
            ->orderBy('next_review_at')
            ->limit($reviewCount)
            ->get();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Word> $reviewWords */
        // property 'word' on UserWord is non-nullable, so return type can be Word
        $reviewWords = $reviewUserWords->map(fn (UserWord $userWord): Word => $userWord->word)->filter();

        // 2. 获取用户已学习的单词ID（该单词书下的）
        $learnedWordIds = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $book->id)
            ->pluck('word_id')
            ->unique();

        // 3. 获取未学习的新单词（排除已学习的，包括复习词）
        /** @var \Illuminate\Database\Eloquent\Collection<int, Word> $newWords */
        $newWords = $book->words()
            ->with('educationLevels')
            ->whereNotIn('words.id', $learnedWordIds)
            ->limit($dailyCount)
            ->get();

        // 4. 合并：复习词在前，新词在后
        $allWords = $reviewWords->merge($newWords);

        return WordResource::collection($allWords);
    }

    /**
     * 获取今日复习单词（艾宾浩斯算法）
     */
    public function getReviewWords(): AnonymousResourceCollection
    {
        $user = Auth::user();
        $setting = $this->getUserSetting($user->id);

        $reviewCount = $setting->daily_new_words * $setting->review_multiplier;

        // 获取需要复习的单词（下次复习时间已到，排除简单词 status=4）
        $userWords = UserWord::where('user_id', $user->id)
            ->whereNotIn('status', [0, 4]) // 已学习且非简单词
            ->where('next_review_at', '<=', now())
            ->with(['word.educationLevels'])
            ->orderBy('next_review_at')
            ->limit($reviewCount)
            ->get();

        $words = $userWords->map(fn ($userWord) => $userWord->word);

        return WordResource::collection($words);
    }

    /**
     * 标记单词（记住/忘记）
     */
    public function markWord(int $id, MarkWordRequest $request): JsonResponse
    {
        $user = Auth::user();
        $remembered = $request->validated()['remembered'];

        $word = Word::findOrFail($id);
        $setting = $this->getUserSetting($user->id);

        // 从用户设置中获取当前单词书ID
        $bookId = $setting->current_book_id;

        DB::transaction(function () use ($user, $word, $remembered, $bookId) {
            // 获取或创建用户单词记录
            $userWord = UserWord::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'word_id' => $word->id,
                    'word_book_id' => $bookId,
                ],
                [
                    'status' => 1, // 学习中
                    'stage' => 0,
                    'ease_factor' => 2.50,
                ]
            );

            // 如果是新单词，设置初始状态
            if ($userWord->status === 0) {
                $userWord->status = 1;
                $userWord->stage = 0;
                $userWord->ease_factor = 2.50;
            }

            // 处理复习结果
            $this->ebbinghausService->processReview($userWord, $remembered);
            $userWord->save();
        });

        return $this->success([], '单词标记成功');
    }

    /**
     * 标记为简单词（已会，不再出现在每日新词和复习中）
     */
    public function markWordAsSimple(int $id): JsonResponse
    {
        $user = Auth::user();
        $word = Word::findOrFail($id);
        $setting = $this->getUserSetting($user->id);

        $bookId = $setting->current_book_id;
        if (! $bookId) {
            return $this->error('请先选择单词书', [], 422);
        }

        $userWord = UserWord::firstOrCreate(
            [
                'user_id' => $user->id,
                'word_id' => $word->id,
                'word_book_id' => $bookId,
            ],
            [
                'status' => 4, // 简单词
                'stage' => 0,
                'ease_factor' => 2.50,
            ]
        );

        $userWord->status = 4;
        $userWord->next_review_at = null; // 永不进入复习
        $userWord->save();

        return $this->success([], '已设为简单词');
    }

    /**
     * 获取学习进度统计
     */
    public function getProgress(): JsonResponse
    {
        $user = Auth::user();
        $setting = $this->getUserSetting($user->id);

        $bookId = $setting->current_book_id;
        if (! $bookId) {
            return $this->success([
                'total_words' => 0,
                'learned_words' => 0,
                'mastered_words' => 0,
                'difficult_words' => 0,
                'simple_words' => 0,
                'progress_percentage' => 0,
            ]);
        }

        $book = Book::find($bookId);
        if (! $book) {
            return $this->error('单词书不存在', [], 404);
        }
        $totalWords = $book->total_words;

        $learnedWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $bookId)
            ->where('status', '!=', 0)
            ->count();

        $masteredWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $bookId)
            ->where('status', 2)
            ->count();

        $difficultWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $bookId)
            ->where('status', 3)
            ->count();

        $simpleWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $bookId)
            ->where('status', 4)
            ->count();

        $progressPercentage = $totalWords > 0
            ? round(($learnedWords / $totalWords) * 100, 2)
            : 0;

        return $this->success([
            'total_words' => $totalWords,
            'learned_words' => $learnedWords,
            'mastered_words' => $masteredWords,
            'difficult_words' => $difficultWords,
            'simple_words' => $simpleWords,
            'progress_percentage' => $progressPercentage,
        ]);
    }

    /**
     * 更新单词数据（修正释义、例句等）
     */
    public function updateWord(int $id): JsonResponse
    {
        $word = Word::findOrFail($id);

        $rules = [
            'example_sentences' => 'sometimes|array',
            'example_sentences.*.en' => 'required_with:example_sentences|string',
            'example_sentences.*.zh' => 'sometimes|string',
            'phonetic_us' => 'sometimes|string|nullable',
            'explanation' => 'sometimes|string|nullable',
        ];

        $validated = request()->validate($rules);

        $word->update($validated);

        return $this->success([
            'word' => new WordResource($word),
        ], '单词更新成功');
    }

    /**
     * 搜索单词
     */
    public function searchWord(string $keyword): JsonResponse
    {
        $keyword = trim($keyword);

        if (empty($keyword)) {
            return $this->error('请输入搜索关键词', [], 422);
        }

        // 精确搜索
        $word = Word::query()
            ->where('content', $keyword)
            ->with('educationLevels')
            ->first();

        if ($word) {
            return $this->success([
                'found' => true,
                'word' => new WordResource($word),
            ]);
        }

        // 未找到
        return $this->success([
            'found' => false,
            'keyword' => $keyword,
        ]);
    }

    /**
     * 创建新单词；若传入 education_level_codes，则按 AI 判断的级别关联教育级别并加入对应单词书
     */
    public function createWord(CreateWordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $word = Word::create([
            'content' => $validated['content'],
            'phonetic_us' => $validated['phonetic_us'] ?? null,
            'explanation' => $validated['explanation'] ?? null,
            'example_sentences' => $validated['example_sentences'] ?? [],
            'difficulty' => 1,
            'frequency' => 1,
        ]);

        $codes = $validated['education_level_codes'] ?? [];
        if (! empty($codes)) {
            $levelIds = EducationLevel::whereIn('code', $codes)->pluck('id')->all();
            if (! empty($levelIds)) {
                $word->educationLevels()->sync($levelIds);
                $books = Book::whereHas('educationLevels', fn ($q) => $q->whereIn('word_education_levels.id', $levelIds))->get();
                foreach ($books as $book) {
                    if ($book->words()->where('word_id', $word->id)->exists()) {
                        continue;
                    }
                    $maxOrder = (int) DB::table('word_book_word')
                        ->where('word_book_id', $book->id)
                        ->max('sort_order');
                    $book->words()->attach($word->id, ['sort_order' => $maxOrder + 1]);
                    $book->updateWordCount();
                }
            }
        }

        return $this->success([
            'word' => new WordResource($word->fresh(['educationLevels'])),
        ], '单词创建成功');
    }

    /**
     * 获取填空练习单词（只从已学过且有例句的单词中获取）
     */
    public function getFillBlankWords(): AnonymousResourceCollection
    {
        $user = Auth::user();
        $setting = $this->getUserSetting($user->id);

        // 获取练习数量，默认为每日新词数量
        $count = $setting->daily_new_words;

        // 获取已学习的单词（排除未学习和简单词，且必须有例句）
        $userWords = UserWord::where('user_id', $user->id)
            ->whereNotIn('status', [0, 4]) // 排除未学习和简单词
            ->with(['word.educationLevels'])
            ->get();

        // 过滤出有例句的单词
        $wordsWithExamples = $userWords->filter(function ($userWord) {
            return $userWord->word
                && $userWord->word->example_sentences
                && is_array($userWord->word->example_sentences)
                && ! empty($userWord->word->example_sentences);
        });

        // 随机选择指定数量的单词
        $selectedWords = $wordsWithExamples
            ->shuffle()
            ->take($count)
            ->map(fn ($userWord) => $userWord->word);

        return WordResource::collection($selectedWords);
    }

    /**
     * 获取用户设置
     */
    private function getUserSetting(int $userId): UserSetting
    {
        return UserSetting::firstOrCreate(
            ['user_id' => $userId],
            [
                'daily_new_words' => 10,
                'review_multiplier' => 2,
                'is_auto_pronounce' => true,
            ]
        );
    }
}
