<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\Crud\TaskCrudService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskCrudService $tasks,
    ) {}

    public function index(Request $request)
    {
        $tasks = Task::query()
            ->orderByRaw("case when status = 'in_progress' then 0 when status = 'todo' then 1 else 2 end")
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();

        return $this->inertia('Tasks/Index', [
            'tasks' => TaskResource::collection($tasks)->toArray($request),
            'stats' => [
                'total' => $tasks->count(),
                'todo' => $tasks->where('status', Task::STATUS_TODO)->count(),
                'in_progress' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->count(),
                'done' => $tasks->where('status', Task::STATUS_DONE)->count(),
            ],
        ]);
    }

    public function create()
    {
        return $this->inertia('Tasks/Create', [
            'submit_url' => route('tasks.store'),
            'statuses' => $this->statuses(),
        ]);
    }

    public function store(StoreTaskRequest $request)
    {
        $this->tasks->create($request->toDto());

        return redirect()->route('tasks.index')
            ->with('success', 'Задача успешно создана.');
    }

    public function edit(Request $request, Task $task)
    {
        return $this->inertia('Tasks/Edit', [
            'submit_url' => route('tasks.update', $task),
            'task' => TaskResource::make($task)->toArray($request),
            'statuses' => $this->statuses(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->tasks->update($task, $request->toDto());

        return redirect()->route('tasks.index')
            ->with('success', 'Задача успешно обновлена.');
    }

    public function destroy(Task $task)
    {
        $this->tasks->delete($task);

        return redirect()->route('tasks.index')
            ->with('success', 'Задача успешно удалена.');
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Task::STATUS_TODO, 'label' => 'Нужно сделать'],
            ['value' => Task::STATUS_IN_PROGRESS, 'label' => 'В работе'],
            ['value' => Task::STATUS_DONE, 'label' => 'Готово'],
        ];
    }
}
