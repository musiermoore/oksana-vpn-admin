<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApiTransactionTelegramMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telegram_chat_id' => ['required', 'integer'],
            'telegram_message_id' => ['required', 'integer'],
        ];
    }
}
