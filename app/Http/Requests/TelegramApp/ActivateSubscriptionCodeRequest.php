<?php

namespace App\Http\Requests\TelegramApp;

use Illuminate\Foundation\Http\FormRequest;

class ActivateSubscriptionCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ];
    }
}
