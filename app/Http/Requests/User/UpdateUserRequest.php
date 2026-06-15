<?php

namespace App\Http\Requests\User;

use App\DTOs\User\UserData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'telegram' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'join_at' => ['required', 'date'],
            'is_active' => ['required', 'boolean'],
            'max_devices' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'traffic_limit_bytes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function toDto(): UserData
    {
        $data = $this->validated();

        return new UserData(
            name: $data['name'],
            telegram: $data['telegram'],
            description: $data['description'] ?? null,
            joinAt: $data['join_at'],
            isActive: (bool) $data['is_active'],
            maxDevices: (int) ($data['max_devices'] ?? 0),
            trafficLimitBytes: (int) ($data['traffic_limit_bytes'] ?? 0),
        );
    }
}
