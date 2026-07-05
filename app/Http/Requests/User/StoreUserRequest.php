<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\DTOs\User\UserData;
use App\Http\Requests\DataFormRequest;

class StoreUserRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'telegram' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'join_at' => ['required', 'date'],
            'create_configs' => ['nullable', 'boolean'],
            'max_devices' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'traffic_limit_bytes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function dtoClass(): string
    {
        return UserData::class;
    }
}
