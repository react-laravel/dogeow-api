<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTranslateWordSync extends Model
{
    protected $fillable = [
        'user_id',
        'known',
        'studying',
        'revision',
        'synced_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'known' => 'array',
        'studying' => 'array',
        'revision' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
