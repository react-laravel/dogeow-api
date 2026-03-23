<?php

namespace App\Models\Word;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Book extends Model
{
    use HasFactory;

    protected $table = 'word_books';

    protected $fillable = [
        'word_category_id',
        'name',
        'description',
        'difficulty',
        'total_words',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'difficulty' => 'integer',
            'total_words' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'word_category_id');
    }

    /**
     * 单词书包含的单词(多对多)
     */
    public function words(): BelongsToMany
    {
        return $this->belongsToMany(Word::class, 'word_book_word', 'word_book_id', 'word_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * 单词书所属的教育级别(多对多)
     */
    public function educationLevels(): BelongsToMany
    {
        return $this->belongsToMany(
            EducationLevel::class,
            'word_book_education_level',
            'word_book_id',
            'education_level_id'
        )->withTimestamps();
    }

    /**
     * 更新单词数量统计
     */
    public function updateWordCount(): void
    {
        $this->update(['total_words' => $this->words()->count()]);
    }
}
