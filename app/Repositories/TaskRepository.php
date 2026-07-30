<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Task;

class TaskRepository
{
    public function create(array $attributes): Task
    {
        return Task::query()->create($attributes);
    }

    public function update(Task $task, array $attributes): Task
    {
        $task->update($attributes);

        return $task->refresh();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }
}
