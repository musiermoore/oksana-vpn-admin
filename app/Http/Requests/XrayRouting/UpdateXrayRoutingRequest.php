<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayRouting;

use App\DTOs\XrayRouting\XrayRoutingData;
use App\Enums\XrayRoutingOutbound;
use App\Http\Requests\DataFormRequest;
use App\Models\XrayRouting;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateXrayRoutingRequest extends DataFormRequest
{
    protected function laravelData(): string
    {
        return XrayRoutingData::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'outbound' => ['required', Rule::enum(XrayRoutingOutbound::class)],
            'subscription_types' => ['required', 'array', 'min:1'],
            'subscription_types.*' => [
                'required',
                'string',
                Rule::in([
                    XrayRouting::SUBSCRIPTION_CONNECT,
                    XrayRouting::SUBSCRIPTION_CONNECT_WL,
                ]),
            ],
            'xray_inbound_ids' => ['present', 'array'],
            'xray_inbound_ids.*' => ['integer', Rule::exists('xray_inbounds', 'id')],
            'external_subscription_config_ids' => ['present', 'array'],
            'external_subscription_config_ids.*' => ['integer', Rule::exists('vless_external_subscription_configs', 'id')],
            'proxy_ids' => ['sometimes', 'array'],
            'proxy_ids.*' => ['integer', Rule::exists('proxies', 'id')],
            'rules_json' => ['required', 'string', 'json'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rules = json_decode((string) $this->input('rules_json'), true);

            if (! is_array($rules)) {
                $validator->errors()->add('rules_json', 'Rules JSON must decode to an object or array.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function dtoPayload(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'outbound' => $validated['outbound'],
            'subscription_types' => array_values(array_unique($validated['subscription_types'])),
            'xray_inbound_ids' => $this->uniqueIntegerList($validated['xray_inbound_ids'] ?? []),
            'external_subscription_config_ids' => $this->uniqueIntegerList($validated['external_subscription_config_ids'] ?? []),
            'proxy_ids' => $this->uniqueIntegerList($validated['proxy_ids'] ?? []),
            'rules' => json_decode((string) $validated['rules_json'], true, 512, JSON_THROW_ON_ERROR),
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, int>
     */
    private function uniqueIntegerList(array $items): array
    {
        return array_values(array_unique(array_map('intval', $items)));
    }
}
