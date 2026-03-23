<?php

namespace App\Models\Word;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EducationLevel extends Model
{
    use HasFactory;

    protected $table = 'word_education_levels';

    protected $fillable = [
        'code',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * 关联的单词(多对多)
     */
    public function words(): BelongsToMany
    {
        return $this->belongsToMany(Word::class, 'word_education_level', 'education_level_id', 'word_id')
            ->withTimestamps();
    }

    /**
     * 关联的单词书(多对多)
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(
            Book::class,
            'word_book_education_level',
            'education_level_id',
            'word_book_id'
        )->withTimestamps();
    }
}
