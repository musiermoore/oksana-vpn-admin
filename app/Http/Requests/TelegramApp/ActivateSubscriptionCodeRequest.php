<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\TelegramApp\ActivateSubscriptionCodeData;
use App\Http\Requests\DataFormRequest;

class ActivateSubscriptionCodeRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ];
    }

    protected function dtoClass(): string
    {
        return ActivateSubscriptionCodeData::class;
    }
}
