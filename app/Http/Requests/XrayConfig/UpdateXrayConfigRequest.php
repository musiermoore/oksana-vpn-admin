<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayConfig;

use App\DTOs\XrayConfig\XrayConfigUpdateData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

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
            'group_id' => ['nullable', 'integer', Rule::exists('config_groups', 'id')],
        ];
    }

    protected function laravelData(): string
    {
        return XrayConfigUpdateData::class;
    }
}
