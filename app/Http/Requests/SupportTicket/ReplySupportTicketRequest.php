<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportTicket;

use App\DTOs\SupportTicket\SupportTicketReplyData;
use App\Enums\SupportTicketStatus;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class ReplySupportTicketRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(SupportTicketStatus::class)],
        ];
    }

    protected function dtoClass(): string
    {
        return SupportTicketReplyData::class;
    }
}
