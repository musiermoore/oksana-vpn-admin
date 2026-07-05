<?php

declare(strict_types=1);

namespace App\Http\Requests\Limit;

use App\DTOs\Limit\LimitData;
use App\Http\Requests\DataFormRequest;

class StoreLimitRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config_id' => ['required', 'exists:configs,id'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function dtoClass(): string
    {
        return LimitData::class;
    }
}
