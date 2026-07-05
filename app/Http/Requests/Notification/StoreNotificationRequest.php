<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\DTOs\Notification\NotificationBroadcastData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Validator;

class StoreNotificationRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'send_to_all' => ['required', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'message_html' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $selectedIds = collect($this->input('user_ids', []))
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->values();

                if (! $this->boolean('send_to_all') && $selectedIds->isEmpty()) {
                    $validator->errors()->add('user_ids', 'Выберите получателей или включите отправку всем.');
                }

                $messageHtml = trim((string) $this->input('message_html', ''));

                if ($messageHtml === '' && ! $this->hasFile('image')) {
                    $validator->errors()->add('message_html', 'Добавьте текст сообщения или изображение.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_to_all' => $this->boolean('send_to_all'),
            'user_ids' => array_values(array_filter(
                (array) $this->input('user_ids', []),
                fn ($value) => $value !== null && $value !== ''
            )),
        ]);
    }

    protected function dtoClass(): string
    {
        return NotificationBroadcastData::class;
    }
}
