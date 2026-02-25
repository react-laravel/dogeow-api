<?php

namespace App\Models\Word;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \App\Models\User $user
 * @property \App\Models\Word\Word $word
 * @property \App\Models\Word\Book $book
 */
class UserWord extends Model
{
    use HasFactory;

    protected $table = 'user_words';

    protected $fillable = [
        'user_id',
        'word_id',
        'word_book_id',
        'status',
        'stage',
        'ease_factor',
        'review_count',
        'correct_count',
        'wrong_count',
        'is_favorite',
        'last_review_at',
        'next_review_at',
        'personal_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'stage' => 'integer',
            'ease_factor' => 'decimal:2',
            'review_count' => 'integer',
            'correct_count' => 'integer',
            'wrong_count' => 'integer',
            'is_favorite' => 'boolean',
            'last_review_at' => 'datetime',
            'next_review_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class, 'word_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'word_book_id');
    }
}
