<?php

namespace App\Http\Requests\TelegramApp;

use App\DTOs\SupportTicket\SupportTicketReplyData;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketMessageRequest extends FormRequest
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

    public function toDto(): SupportTicketReplyData
    {
        return new SupportTicketReplyData(
            message: trim((string) $this->validated('message')),
        );
    }
}
