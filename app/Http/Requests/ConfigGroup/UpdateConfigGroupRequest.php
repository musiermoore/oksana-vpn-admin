<?php

declare(strict_types=1);

namespace App\Http\Requests\ConfigGroup;

use App\DTOs\ConfigGroup\ConfigGroupData;
use App\Http\Requests\DataFormRequest;

final class UpdateConfigGroupRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_default' => ['required', 'boolean'],
        ];
    }

    protected function laravelData(): string
    {
        return ConfigGroupData::class;
    }
}
