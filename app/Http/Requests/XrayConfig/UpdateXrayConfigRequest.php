<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayConfig;

use App\DTOs\XrayConfig\XrayConfigUpdateData;
use App\Http\Requests\DataFormRequest;

class UpdateXrayConfigRequest extends DataFormRequest
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
        return XrayConfigUpdateData::class;
    }
}
