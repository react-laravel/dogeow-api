<?php

namespace App\Models\Todo;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property int $position
 */
class TodoList extends Model
{
    protected $table = 'todo_lists';

    protected $fillable = ['user_id', 'name', 'description', 'position'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TodoTask::class, 'todo_list_id')->orderBy('position');
    }
}
