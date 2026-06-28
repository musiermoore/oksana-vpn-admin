<?php

namespace App\Http\Requests\TelegramApp;

use App\DTOs\TelegramApp\TelegramAppAuthData;
use Illuminate\Foundation\Http\FormRequest;

class AuthenticateTelegramAppRequest extends FormRequest
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

    public function toDto(): TelegramAppAuthData
    {
        return new TelegramAppAuthData(
            initData: trim((string) $this->validated('init_data')),
            startParam: null,
        );
    }
}
