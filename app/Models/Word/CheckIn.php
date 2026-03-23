<?php

namespace App\Models\Word;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    use HasFactory;

    protected $table = 'user_word_check_ins';

    protected $fillable = [
        'user_id',
        'check_in_date',
        'new_words_count',
        'review_words_count',
        'study_duration',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'new_words_count' => 'integer',
            'review_words_count' => 'integer',
            'study_duration' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
