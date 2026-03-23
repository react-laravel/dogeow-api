<?php

namespace App\Models\Note;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'target_id',
        'type',
    ];

    /**
     * 获取源节点
     */
    public function sourceNote(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'source_id');
    }

    /**
     * 获取目标节点
     */
    public function targetNote(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'target_id');
    }
}
