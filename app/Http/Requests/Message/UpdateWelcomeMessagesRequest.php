<?php

namespace App\Http\Requests\Message;

use App\DTOs\Message\WelcomeMessagesData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWelcomeMessagesRequest extends FormRequest
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

    public function toDto(): WelcomeMessagesData
    {
        $data = $this->validated();

        return new WelcomeMessagesData(
            basicText: (string) ($data['basic_text'] ?? ''),
            extendedText: (string) ($data['extended_text'] ?? ''),
        );
    }
}
