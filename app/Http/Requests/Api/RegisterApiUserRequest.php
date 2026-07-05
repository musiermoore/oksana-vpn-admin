<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\DTOs\User\ApiUserRegistrationData;
use App\Http\Requests\DataFormRequest;

class RegisterApiUserRequest extends DataFormRequest
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

    protected function dtoClass(): string
    {
        return ApiUserRegistrationData::class;
    }
}
