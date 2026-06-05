<?php

namespace App\Http\Requests\XrayConfig;

use App\DTOs\XrayConfig\XrayConfigUpdateData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateXrayConfigRequest extends FormRequest
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

    public function toDto(): XrayConfigUpdateData
    {
        $data = $this->validated();

        return new XrayConfigUpdateData(
            userId: (int) $data['user_id'],
        );
    }
}
