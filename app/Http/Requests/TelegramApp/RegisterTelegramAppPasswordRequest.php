<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\TelegramApp\TelegramAppPasswordRegistrationData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class RegisterTelegramAppPasswordRequest extends DataFormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'login' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'login')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    protected function dtoClass(): string
    {
        return TelegramAppPasswordRegistrationData::class;
    }
}
