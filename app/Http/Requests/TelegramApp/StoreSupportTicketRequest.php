<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\SupportTicket\SupportTicketStoreData;
use App\Http\Requests\DataFormRequest;

class StoreSupportTicketRequest extends DataFormRequest
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

    protected function dtoClass(): string
    {
        return SupportTicketStoreData::class;
    }
}
