<?php

namespace App\Http\Controllers\Api\Todo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Todo\ReorderTodoTasksRequest;
use App\Http\Requests\Todo\TodoListRequest;
use App\Http\Requests\Todo\TodoTaskRequest;
use App\Models\Todo\TodoList;
use App\Models\Todo\TodoTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodoListController extends Controller
{
    /**
     * 当前用户的所有待办列表(含任务，按 position 排序)
     */
    public function index(): JsonResponse
    {
        $lists = TodoList::with('tasks')
            ->where('user_id', $this->getCurrentUserId())
            ->orderBy('position')
            ->get();

        return response()->json($lists);
    }

    /**
     * 单个待办列表(含任务)
     */
    public function show(string $id): JsonResponse
    {
        $list = $this->findUserList($id);
        $list->load('tasks');

        return response()->json($list);
    }

    /**
     * 创建待办列表
     */
    public function store(TodoListRequest $request): JsonResponse
    {
        $userId = $this->getCurrentUserId();
        $maxPosition = TodoList::where('user_id', $userId)->max('position') ?? -1;

        $list = TodoList::create([
            'user_id' => $userId,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'position' => $maxPosition + 1,
        ]);

        return response()->json($list, 201);
    }

    /**
     * 更新待办列表
     */
    public function update(TodoListRequest $request, string $id): JsonResponse
    {
        $list = $this->findUserList($id);
        $list->update($request->validated());

        return response()->json($list);
    }

    /**
     * 删除待办列表(及其任务)
     */
    public function destroy(string $id): JsonResponse
    {
        $list = $this->findUserList($id);
        TodoTask::where('todo_list_id', $list->id)->delete();
        $list->delete();

        return response()->json([], 204);
    }

    /**
     * 在列表中创建任务
     */
    public function storeTask(Request $request, string $id): JsonResponse
    {
        $request->validate(['title' => 'required|string|max:1000']);

        $list = $this->findUserList($id);
        $maxPosition = TodoTask::where('todo_list_id', $list->id)->max('position') ?? -1;

        $task = TodoTask::create([
            'todo_list_id' => $list->id,
            'title' => $request->input('title'),
            'is_completed' => false,
            'position' => $maxPosition + 1,
        ]);

        return response()->json($task, 201);
    }

    /**
     * 更新任务(标题、完成状态、排序)
     */
    public function updateTask(TodoTaskRequest $request, string $listId, string $taskId): JsonResponse
    {
        $list = $this->findUserList($listId);
        $task = TodoTask::where('todo_list_id', $list->id)->findOrFail($taskId);

        $data = $request->validated();
        if (array_key_exists('title', $data)) {
            $task->title = $data['title'];
        }
        if (array_key_exists('is_completed', $data)) {
            $task->is_completed = $data['is_completed'];
        }
        if (array_key_exists('position', $data)) {
            $task->position = $data['position'];
        }
        $task->save();

        return response()->json($task);
    }

    /**
     * 删除任务
     */
    public function destroyTask(string $listId, string $taskId): JsonResponse
    {
        $list = $this->findUserList($listId);
        $task = TodoTask::where('todo_list_id', $list->id)->findOrFail($taskId);
        $task->delete();

        return response()->json([], 204);
    }

    /**
     * 批量重排任务顺序(传 task_ids 顺序即新 position 0,1,2...)
     */
    public function reorderTasks(ReorderTodoTasksRequest $request, string $listId): JsonResponse
    {
        $list = $this->findUserList($listId);
        $taskIds = $request->validated('task_ids');

        foreach ($taskIds as $position => $taskId) {
            TodoTask::where('todo_list_id', $list->id)
                ->where('id', $taskId)
                ->update(['position' => $position]);
        }

        $list->load('tasks');

        return response()->json($list);
    }

    private function findUserList(string $id): TodoList
    {
        $list = TodoList::find($id);

        if (! $list || $list->user_id !== $this->getCurrentUserId()) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No query results for model [App\\Models\\Todo\\TodoList] ' . $id
            );
        }

        return $list;
    }
}
