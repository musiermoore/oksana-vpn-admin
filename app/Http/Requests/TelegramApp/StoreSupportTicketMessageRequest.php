<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\SupportTicket\SupportTicketReplyData;
use App\Http\Requests\DataFormRequest;

class StoreSupportTicketMessageRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function dtoClass(): string
    {
        return SupportTicketReplyData::class;
    }
}
