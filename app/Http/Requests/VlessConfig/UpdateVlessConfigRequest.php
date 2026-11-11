<?php

declare(strict_types=1);

namespace App\Http\Requests\VlessConfig;

use App\DTOs\VlessConfig\VlessConfigUpdateData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class UpdateVlessConfigRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'group_id' => ['nullable', 'integer', Rule::exists('config_groups', 'id')],
        ];
    }

    protected function laravelData(): string
    {
        return VlessConfigUpdateData::class;
    }

    protected function dtoPayload(): array
    {
        return [...$this->validated(), 'group_id' => $this->input('group_id') ?: null];
    }
}
