<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\TelegramApp\TelegramAppAuthData;
use App\Http\Requests\DataFormRequest;

class AuthenticateTelegramAppRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'init_data' => ['required', 'string'],
        ];
    }

    protected function dtoClass(): string
    {
        return TelegramAppAuthData::class;
    }
}
