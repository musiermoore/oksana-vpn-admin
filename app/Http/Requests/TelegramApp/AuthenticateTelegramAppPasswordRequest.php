<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\TelegramApp\TelegramAppPasswordAuthData;
use App\Http\Requests\DataFormRequest;

class AuthenticateTelegramAppPasswordRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    protected function dtoClass(): string
    {
        return TelegramAppPasswordAuthData::class;
    }
}
