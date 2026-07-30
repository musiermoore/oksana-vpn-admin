<?php

declare(strict_types=1);

namespace App\Services\Crud;

use App\DTOs\Task\TaskData;
use App\Models\Task;
use App\Repositories\TaskRepository;

class TaskCrudService
{
    public function __construct(
        private readonly TaskRepository $tasks,
    ) {}

    public function create(TaskData $data): Task
    {
        return $this->tasks->create($this->attributes($data));
    }

    public function update(Task $task, TaskData $data): Task
    {
        return $this->tasks->update($task, $this->attributes($data));
    }

    public function delete(Task $task): void
    {
        $this->tasks->delete($task);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(TaskData $data): array
    {
        return [
            'title' => $data->title,
            'description' => $data->description,
            'status' => $data->status,
            'due_date' => $data->dueDate,
        ];
    }
}
