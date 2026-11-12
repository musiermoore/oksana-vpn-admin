<?php

declare(strict_types=1);

namespace App\Http\Requests\VlessConfig;

use App\DTOs\VlessConfig\VlessConfigStoreData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class StoreVlessConfigRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'server_id' => ['required', 'exists:servers,id'],
            'inbound_id' => ['required', 'integer', 'min:1'],
            'group_id' => ['nullable', 'integer', Rule::exists('config_groups', 'id')],
            'subtitle' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function laravelData(): string
    {
        return VlessConfigStoreData::class;
    }

    protected function dtoPayload(): array
    {
        return [...$this->validated(), 'group_id' => $this->input('group_id') ?: null];
    }
}
