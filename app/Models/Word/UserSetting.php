<?php

namespace App\Models\Word;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory;

    protected $table = 'user_word_settings';

    protected $fillable = [
        'user_id',
        'daily_new_words',
        'review_multiplier',
        'current_book_id',
        'is_auto_pronounce',
    ];

    protected function casts(): array
    {
        return [
            'daily_new_words' => 'integer',
            'review_multiplier' => 'integer',
            'is_auto_pronounce' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'current_book_id');
    }
}
