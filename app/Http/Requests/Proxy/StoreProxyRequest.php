<?php

declare(strict_types=1);

namespace App\Http\Requests\Proxy;

use App\DTOs\Proxy\ProxyData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class StoreProxyRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'inbound_id' => ['nullable', 'integer', 'min:1'],
            'is_https' => ['required', 'boolean'],
            'is_ready' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'server_ids' => ['array'],
            'server_ids.*' => ['integer', Rule::exists('servers', 'id')],
        ];
    }

    protected function dtoClass(): string
    {
        return ProxyData::class;
    }
}
