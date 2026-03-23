<?php

namespace App\Models\Todo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $todo_list_id
 * @property string $title
 * @property bool $is_completed
 * @property int $position
 */
class TodoTask extends Model
{
    protected $table = 'todo_tasks';

    protected $fillable = ['todo_list_id', 'title', 'is_completed', 'position'];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function todoList(): BelongsTo
    {
        return $this->belongsTo(TodoList::class, 'todo_list_id');
    }
}
