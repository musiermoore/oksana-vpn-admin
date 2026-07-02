<?php

namespace App\Http\Requests\Proxy;

use App\DTOs\Proxy\ProxyData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProxyRequest extends FormRequest
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
            'is_https' => ['required', 'boolean'],
            'is_ready' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'server_ids' => ['array'],
            'server_ids.*' => ['integer', Rule::exists('servers', 'id')],
        ];
    }

    public function toDto(): ProxyData
    {
        $data = $this->validated();

        return new ProxyData(
            name: (string) $data['name'],
            host: (string) $data['host'],
            port: (int) $data['port'],
            isHttps: (bool) $data['is_https'],
            isReady: (bool) $data['is_ready'],
            description: $data['description'] ?? null,
            serverIds: collect($data['server_ids'] ?? [])
                ->map(fn (mixed $id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
        );
    }
}
