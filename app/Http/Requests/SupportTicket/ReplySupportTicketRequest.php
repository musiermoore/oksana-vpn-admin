<?php

namespace App\Http\Requests\SupportTicket;

use App\Enums\SupportTicketStatus;
use App\DTOs\SupportTicket\SupportTicketReplyData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplySupportTicketRequest extends FormRequest
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

    public function toDto(): SupportTicketReplyData
    {
        $data = $this->validated();

        return new SupportTicketReplyData(
            message: trim((string) $data['message']),
            status: isset($data['status']) ? SupportTicketStatus::from((string) $data['status']) : null,
        );
    }
}
