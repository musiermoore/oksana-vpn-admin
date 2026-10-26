<?php

declare(strict_types=1);

namespace App\Http\Requests\ServerConsole;

use App\DTOs\ServerConsole\ServerConsoleCommandData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Contracts\Validation\Validator;

class StoreServerConsoleCommandRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return [
            'command' => ['nullable', 'string', 'max:1000000'],
            'template_id' => ['nullable', 'integer', 'exists:server_command_templates,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasCommand = trim((string) $this->input('command')) !== '';
            $hasTemplate = (int) $this->input('template_id', 0) > 0;
            if ($hasCommand === $hasTemplate) {
                $validator->errors()->add('command', 'Provide either a command or a template.');
            }
        });
    }

    protected function laravelData(): string
    {
        return ServerConsoleCommandData::class;
    }
}
