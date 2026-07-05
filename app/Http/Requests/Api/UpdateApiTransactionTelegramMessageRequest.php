<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\DTOs\Api\ApiTransactionTelegramMessageData;
use App\Http\Requests\DataFormRequest;

class UpdateApiTransactionTelegramMessageRequest extends DataFormRequest
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

    protected function dtoClass(): string
    {
        return ApiTransactionTelegramMessageData::class;
    }
}
