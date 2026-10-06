<?php

declare(strict_types=1);

namespace App\Http\Requests\XuiDebug;

use App\DTOs\XuiDebug\ExecuteXuiDebugData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class ExecuteXuiDebugRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'server_id' => [
                'required',
                'integer',
                Rule::exists('servers', 'id')->where('is_active', true),
            ],
            'preset' => ['required', 'string'],
            'method' => ['required', 'string', 'in:GET,POST,PUT,PATCH,DELETE'],
            'endpoint' => ['required', 'string', 'max:1000'],
            'encoding' => ['required', 'string', 'in:form,json'],
            'payload' => ['nullable', 'string'],
        ];
    }

    protected function laravelData(): string
    {
        return ExecuteXuiDebugData::class;
    }
}
