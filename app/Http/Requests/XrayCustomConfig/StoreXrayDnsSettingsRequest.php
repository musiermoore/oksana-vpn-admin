<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayCustomConfig;

use App\DTOs\XrayCustomConfig\XrayDnsSettingsData;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Validator;

class StoreXrayDnsSettingsRequest extends DataFormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'servers' => ['required', 'array', 'min:1'],
            'servers.*' => ['required'],
            'servers.*.address' => ['sometimes', 'string', 'max:2000'],
            'servers.*.domains' => ['sometimes', 'array'],
            'servers.*.domains.*' => ['string', 'max:255'],
            'servers.*.skipFallback' => ['sometimes', 'boolean'],
            'query_strategy' => ['required', 'string', 'in:AsIs,UseIP,UseIPv4,UseIPv6'],
            'enable_parallel_query' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    protected function laravelData(): string
    {
        return XrayDnsSettingsData::class;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('servers', []) as $index => $server) {
                if (is_string($server)) {
                    if (mb_strlen($server) > 255) {
                        $validator->errors()->add("servers.{$index}", 'The DNS server must not be longer than 255 characters.');
                    }

                    continue;
                }

                if (! is_array($server)) {
                    $validator->errors()->add("servers.{$index}", 'The DNS server must be a string or object.');
                    continue;
                }

                if (! is_string($server['address'] ?? null) || trim($server['address']) === '') {
                    $validator->errors()->add("servers.{$index}.address", 'The DNS server address is required.');
                }
            }
        });
    }
}
