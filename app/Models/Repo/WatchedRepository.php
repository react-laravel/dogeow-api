<?php

namespace App\Models\Repo;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WatchedRepository extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'owner',
        'repo',
        'full_name',
        'html_url',
        'default_branch',
        'language',
        'ecosystem',
        'package_name',
        'manifest_path',
        'latest_version',
        'latest_source_type',
        'latest_release_url',
        'description',
        'latest_release_published_at',
        'last_checked_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'latest_release_published_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(RepositoryUpdate::class)->latest('published_at');
    }

    public function latestUpdate(): HasOne
    {
        return $this->hasOne(RepositoryUpdate::class)->latestOfMany('published_at');
    }
}
