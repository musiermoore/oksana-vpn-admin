<?php

declare(strict_types=1);

namespace App\Http\Requests\ServerConsole;

use App\DTOs\ServerConsole\ServerConsoleInputData;
use App\Http\Requests\DataFormRequest;

class StoreServerConsoleInputRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return ['input' => ['present', 'string', 'min:1', 'max:4096']];
    }

    public function validationData(): array
    {
        $payload = json_decode($this->getContent(), true);

        if (is_array($payload) && array_key_exists('input', $payload)) {
            return ['input' => $payload['input']];
        }

        return parent::validationData();
    }

    protected function laravelData(): string
    {
        return ServerConsoleInputData::class;
    }
}
