<?php

namespace App\Http\Requests\Api;

use App\DTOs\User\ApiUserRegistrationData;
use Illuminate\Foundation\Http\FormRequest;

class RegisterApiUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('telegram_id') && $this->route('telegramId')) {
            $this->merge([
                'telegram_id' => (string) $this->route('telegramId'),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telegram' => ['nullable', 'string', 'max:255'],
            'telegram_id' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'start_param' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDto(?string $telegramId = null): ApiUserRegistrationData
    {
        $data = $this->validated();

        return new ApiUserRegistrationData(
            telegramId: trim($telegramId ?? $data['telegram_id']),
            telegram: trim((string) ($data['telegram'] ?? '')),
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            startParam: isset($data['start_param']) ? trim((string) $data['start_param']) : null,
        );
    }
}
