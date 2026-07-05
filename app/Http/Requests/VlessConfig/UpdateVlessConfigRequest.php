<?php

declare(strict_types=1);

namespace App\Http\Requests\VlessConfig;

use App\DTOs\VlessConfig\VlessConfigUpdateData;
use App\Http\Requests\DataFormRequest;

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
        ];
    }

    protected function dtoClass(): string
    {
        return VlessConfigUpdateData::class;
    }
}
