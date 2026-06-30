<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Word\CreateWordRequest;
use App\Http\Requests\Word\EstimateVocabularyRequest;
use App\Http\Requests\Word\MarkWordRequest;
use App\Http\Resources\Word\WordResource;
use App\Models\User;
use App\Models\Word\Book;
use App\Models\Word\EducationLevel;
use App\Models\Word\UserSetting;
use App\Models\Word\UserWord;
use App\Models\Word\Word;
use App\Services\Word\EbbinghausService;
use App\Services\Word\VocabularyEstimateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LearningController extends Controller
{
    public function __construct(
        private readonly EbbinghausService $ebbinghausService,
        private readonly VocabularyEstimateService $vocabularyEstimateService,
    ) {}

    /**
     * 获取今日学习单词(包含复习词和新词)
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

        // 1. 到期复习词（限制在当前单词书）
        $reviewUserWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $book->id)
            ->whereNotIn('status', [0, 4]) // 已学习且非简单词
            ->where('next_review_at', '<=', now())
            ->with(['word.educationLevels'])
            ->orderBy('next_review_at')
            ->limit($reviewCount)
            ->get();

        // 2. 今日刚标记「记不住」、尚未到复习时间的词（允许当天继续练）
        $sameDayRetryUserWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $book->id)
            ->whereNotIn('status', [0, 4])
            ->where('wrong_count', '>', 0)
            ->where('next_review_at', '>', now())
            ->whereDate('last_review_at', today())
            ->with(['word.educationLevels'])
            ->orderBy('last_review_at')
            ->limit($reviewCount)
            ->get();

        $reviewWordIds = $reviewUserWords->pluck('word_id')
            ->merge($sameDayRetryUserWords->pluck('word_id'))
            ->unique();

        /** @var Collection<int, Word> $reviewWords */
        $reviewWords = $reviewUserWords
            ->merge($sameDayRetryUserWords)
            ->unique('word_id')
            ->map(function (UserWord $userWord): Word {
                $word = $userWord->word;
                $word->setAttribute('is_review_word', true);

                return $word;
            })
            ->filter()
            ->take($reviewCount)
            ->values();

        // 3. 获取用户已学习的单词 ID(该单词书下的)
        $learnedWordIds = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $book->id)
            ->pluck('word_id')
            ->unique();

        // 4. 获取未学习的新单词(排除已学习的，包括复习词)
        /** @var Collection<int, Word> $newWords */
        $newWords = $book->words()
            ->with('educationLevels')
            ->whereNotIn('words.id', $learnedWordIds)
            ->whereNotIn('words.id', $reviewWordIds)
            ->orderBy('word_book_word.sort_order')
            ->limit($dailyCount)
            ->get();

        foreach ($newWords as $newWord) {
            $newWord->setAttribute('is_review_word', false);
        }

        // 5. 合并：复习词在前，新词在后
        $allWords = $reviewWords->merge($newWords);

        return WordResource::collection($allWords);
    }

    /**
     * 获取今日复习单词(艾宾浩斯算法)
     */
    public function getReviewWords(): AnonymousResourceCollection
    {
        $user = Auth::user();
        $setting = $this->getUserSetting($user->id);

        $reviewCount = $setting->daily_new_words * $setting->review_multiplier;

        // 获取需要复习的单词(下次复习时间已到，排除简单词 status=4)
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
     * 标记单词(记住/忘记)
     */
    public function markWord(int $id, MarkWordRequest $request): JsonResponse
    {
        $user = Auth::user();
        $remembered = $request->validated()['remembered'];

        $word = Word::findOrFail($id);
        $setting = $this->getUserSetting($user->id);

        // 从用户设置中获取当前单词书 ID
        $bookId = $setting->current_book_id;

        $existingUserWord = UserWord::query()
            ->where('user_id', $user->id)
            ->where('word_id', $word->id)
            ->where('word_book_id', $bookId)
            ->first();

        // 首次遇见的新词点「记不住」时不写入学习记录，避免从「新词池」被提前移除
        if (! $remembered && ! $existingUserWord) {
            return $this->success([], '单词标记成功');
        }

        DB::transaction(function () use ($user, $word, $remembered, $bookId, $existingUserWord) {
            $userWord = $existingUserWord ?? UserWord::query()->create([
                'user_id' => $user->id,
                'word_id' => $word->id,
                'word_book_id' => $bookId,
                'status' => 1,
                'stage' => 0,
                'ease_factor' => 2.50,
            ]);

            if ($userWord->status === 0) {
                $userWord->status = 1;
                $userWord->stage = 0;
                $userWord->ease_factor = 2.50;
            }

            $this->ebbinghausService->processReview($userWord, $remembered);
            $userWord->save();
        });

        return $this->success([], '单词标记成功');
    }

    /**
     * 标记为简单词(已会，不再出现在每日新词和复习中)
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

        // Single query for all status counts (was 4 separate count queries)
        $statusCounts = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $bookId)
            ->selectRaw('
                SUM(CASE WHEN status != 0 THEN 1 ELSE 0 END) as learned_words,
                SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as mastered_words,
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) as difficult_words,
                SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) as simple_words
            ')
            ->first();

        $learnedWords = (int) ($statusCounts->learned_words ?? 0);
        $masteredWords = (int) ($statusCounts->mastered_words ?? 0);
        $difficultWords = (int) ($statusCounts->difficult_words ?? 0);
        $simpleWords = (int) ($statusCounts->simple_words ?? 0);

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
     * 更新单词数据(修正释义、例句等)
     */
    public function updateWord(int $id): JsonResponse
    {
        // words 是全局共享词库（无 user_id），仅管理员可修改，
        // 避免任意认证用户篡改所有人共用的释义/例句
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null || ! $user->isAdmin()) {
            return $this->error('无权修改全局单词', [], 403);
        }

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
     * 获取填空练习单词(只从已学过且有例句的单词中获取)
     */
    public function getFillBlankWords(): AnonymousResourceCollection
    {
        $user = Auth::user();
        $setting = $this->getUserSetting($user->id);

        // 获取练习数量，默认为每日新词数量
        $count = $setting->daily_new_words;

        // 获取已学习的单词(排除未学习和简单词，且必须有例句)
        // 使用 whereHas 在数据库层面过滤，避免全量加载
        $userWords = UserWord::where('user_id', $user->id)
            ->whereNotIn('status', [0, 4]) // 排除未学习和简单词
            ->whereHas('word', function ($query) {
                $query->whereNotNull('example_sentences')
                    ->where('example_sentences', '!=', '[]')
                    ->where('example_sentences', '!=', '');
            })
            ->with(['word.educationLevels'])
            ->limit($count * 3)
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
     * 估算用户词汇量
     */
    public function estimateVocabulary(EstimateVocabularyRequest $request): JsonResponse
    {
        $result = $this->vocabularyEstimateService->estimate($request->validated()['answers']);

        return $this->success($result);
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
