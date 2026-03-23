<?php

namespace App\Models\Repo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'watched_repository_id',
        'source_type',
        'source_id',
        'version',
        'title',
        'release_url',
        'body',
        'ai_summary',
        'published_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function watchedRepository(): BelongsTo
    {
        return $this->belongsTo(WatchedRepository::class);
    }
}
