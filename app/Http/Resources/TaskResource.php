<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Task $task */
        $task = $this->resource;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'status_label' => match ($task->status) {
                Task::STATUS_TODO => 'Нужно сделать',
                Task::STATUS_IN_PROGRESS => 'В работе',
                Task::STATUS_DONE => 'Готово',
                default => $task->status,
            },
            'due_date' => $task->due_date?->format('Y-m-d'),
            'due_date_label' => $task->due_date?->format('d.m.Y'),
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'links' => [
                'edit' => route('tasks.edit', $task),
                'destroy' => route('tasks.destroy', $task),
            ],
        ];
    }
}
