<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\DTOs\Task\TaskData;
use App\Http\Requests\DataFormRequest;
use App\Models\Task;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(Task::statuses())],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function additionalDtoData(): array
    {
        return [
            'dueDate' => $this->validated('due_date'),
        ];
    }

    protected function dtoClass(): string
    {
        return TaskData::class;
    }
}
