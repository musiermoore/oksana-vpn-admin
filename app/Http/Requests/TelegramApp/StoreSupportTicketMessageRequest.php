<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use Closure;
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
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['bail', 'file', 'max:5120', static function (string $attribute, mixed $value, Closure $fail): void {
                $mimeType = $value->getMimeType();

                if ($mimeType !== 'application/pdf' && (! str_starts_with($mimeType, 'image/') || $mimeType === 'image/svg+xml')) {
                    $fail('Можно прикрепить изображение или PDF-файл.');
                }
            }],
        ];
    }

    protected function laravelData(): string
    {
        return SupportTicketReplyData::class;
    }

    protected function dtoPayload(): array
    {
        return ['message' => $this->validated('message')];
    }
}
