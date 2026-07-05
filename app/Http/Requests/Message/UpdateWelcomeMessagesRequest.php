<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use App\DTOs\Message\WelcomeMessagesData;
use App\Http\Requests\DataFormRequest;

class UpdateWelcomeMessagesRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'basic_text' => ['nullable', 'string', 'max:20000'],
            'extended_text' => ['nullable', 'string', 'max:20000'],
        ];
    }

    protected function dtoClass(): string
    {
        return WelcomeMessagesData::class;
    }
}
