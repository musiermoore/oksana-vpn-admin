<?php

namespace App\Http\Requests\Config;

use App\DTOs\Config\ConfigUpdateData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigRequest extends FormRequest
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

    public function toDto(): ConfigUpdateData
    {
        $data = $this->validated();

        return new ConfigUpdateData(
            userId: (int) $data['user_id'],
            description: $data['description'] ?? null,
        );
    }
}
