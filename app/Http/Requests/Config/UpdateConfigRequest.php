<?php

declare(strict_types=1);

namespace App\Http\Requests\Config;

use App\DTOs\Config\ConfigUpdateData;
use App\Http\Requests\DataFormRequest;

class UpdateConfigRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'description' => ['nullable', 'string'],
        ];
    }

    protected function dtoClass(): string
    {
        return ConfigUpdateData::class;
    }
}
