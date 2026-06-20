<?php

namespace App\Models\Book;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookMark extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'book_id',
        'kind',
        'chapter_id',
        'chapter_title',
        'scroll_top',
        'pair_index',
        'position_key',
        'excerpt',
        'note',
        'created_at_ms',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'chapter_id' => 'integer',
        'scroll_top' => 'float',
        'pair_index' => 'integer',
        'created_at_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
