<?php

namespace App\Models\Word;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property mixed $id
 * @property mixed $content
 * @property mixed $explanation
 * @property mixed $example_sentences
 * @property mixed $user_id
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Word\EducationLevel> $educationLevels
 */
class Word extends Model
{
    use HasFactory;

    protected $table = 'words';

    protected $fillable = [
        'content',
        'phonetic_us',
        'explanation',
        'example_sentences',
        'difficulty',
        'frequency',
    ];

    protected function casts(): array
    {
        return [
            'example_sentences' => 'array',
            'difficulty' => 'integer',
            'frequency' => 'integer',
        ];
    }

    /**
     * 单词所属的单词书（多对多）
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'word_book_word', 'word_id', 'word_book_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function userWords(): HasMany
    {
        return $this->hasMany(UserWord::class, 'word_id');
    }

    /**
     * 单词所属的教育级别（多对多）
     */
    public function educationLevels(): BelongsToMany
    {
        return $this->belongsToMany(EducationLevel::class, 'word_education_level', 'word_id', 'education_level_id')
            ->withTimestamps();
    }

    /**
     * 根据单词内容查找或创建单词
     */
    public static function findOrCreateByContent(string $content, array $attributes = []): self
    {
        return static::firstOrCreate(
            ['content' => $content],
            $attributes
        );
    }
}
