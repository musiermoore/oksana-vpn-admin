<?php

declare(strict_types=1);

namespace App\Http\Requests\ServerConsole;

use App\DTOs\ServerConsole\ServerConsoleResizeData;
use App\Http\Requests\DataFormRequest;

class StoreServerConsoleResizeRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'columns' => ['required', 'integer', 'between:20,300'],
            'rows' => ['required', 'integer', 'between:5,100'],
        ];
    }

    protected function laravelData(): string
    {
        return ServerConsoleResizeData::class;
    }
}
