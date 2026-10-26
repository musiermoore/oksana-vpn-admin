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
        return ['input' => ['required', 'string', 'max:4096']];
    }

    protected function laravelData(): string
    {
        return ServerConsoleInputData::class;
    }
}
