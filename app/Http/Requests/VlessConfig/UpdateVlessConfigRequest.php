<?php

namespace App\Http\Requests\VlessConfig;

use App\DTOs\VlessConfig\VlessConfigUpdateData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVlessConfigRequest extends FormRequest
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

    public function toDto(): VlessConfigUpdateData
    {
        $data = $this->validated();

        return new VlessConfigUpdateData(userId: (int) $data['user_id']);
    }
}
