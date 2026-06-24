<?php

namespace App\Http\Requests\TelegramApp;

use App\DTOs\SupportTicket\SupportTicketStoreData;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    public function toDto(): SupportTicketStoreData
    {
        $data = $this->validated();

        return new SupportTicketStoreData(
            subject: isset($data['subject']) ? trim((string) $data['subject']) : null,
            message: trim((string) $data['message']),
        );
    }
}
