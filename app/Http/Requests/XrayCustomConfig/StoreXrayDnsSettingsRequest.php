<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayCustomConfig;

use App\DTOs\XrayCustomConfig\XrayDnsSettingsData;
use App\Http\Requests\DataFormRequest;

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
            'servers.*' => ['required', 'string', 'max:255'],
            'query_strategy' => ['required', 'string', 'in:AsIs,UseIP,UseIPv4,UseIPv6'],
            'enable_parallel_query' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }

    protected function laravelData(): string
    {
        return XrayDnsSettingsData::class;
    }
}
