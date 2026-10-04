<?php

declare(strict_types=1);

namespace App\Http\Requests\XrayCustomConfig;

use App\DTOs\XrayCustomConfig\XrayCustomConfigData;
use App\Enums\XrayBalancerStrategy;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateXrayCustomConfigRequest extends DataFormRequest
{
    protected function dtoClass(): string
    {
        return XrayCustomConfigData::class;
    }

    public function rules(): array
    {
        $configId = $this->route('xrayCustomConfig')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('xray_custom_configs', 'slug')->ignore($configId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'dns_settings_id' => ['nullable', 'integer', Rule::exists('xray_routing_dns_settings', 'id')],
            'geodata_id' => ['nullable', 'integer', Rule::exists('xray_routing_geodata', 'id')],
            'xray_inbound_ids' => ['present', 'array'],
            'xray_inbound_ids.*' => ['integer', Rule::exists('xray_inbounds', 'id')],
            'external_subscription_config_ids' => ['present', 'array'],
            'external_subscription_config_ids.*' => ['integer', Rule::exists('vless_external_subscription_configs', 'id')],
            'proxy_ids' => ['present', 'array'],
            'proxy_ids.*' => ['integer', Rule::exists('proxies', 'id')],
            'xray_routing_ids' => ['present', 'array'],
            'xray_routing_ids.*' => ['integer', Rule::exists('xray_routings', 'id')],
            'base_settings_json' => ['required', 'string', 'json'],
            'outbound_groups_json' => ['required', 'string', 'json'],
            'routes_json' => ['required', 'string', 'json'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! is_array(json_decode((string) $this->input('base_settings_json'), true))) {
                $validator->errors()->add('base_settings_json', 'Base settings must decode to a JSON object.');
            }
            foreach (['outbound_groups_json', 'routes_json'] as $field) {
                if (! is_array(json_decode((string) $this->input($field), true))) {
                    $validator->errors()->add($field, 'Value must decode to a JSON array.');
                }
            }

            $groups = json_decode((string) $this->input('outbound_groups_json'), true);
            foreach (is_array($groups) ? $groups : [] as $index => $group) {
                if (! is_array($group) || ! in_array(
                    $group['strategy'] ?? null,
                    array_map(static fn (XrayBalancerStrategy $strategy): string => $strategy->value, XrayBalancerStrategy::cases()),
                    true,
                )) {
                    $validator->errors()->add("outbound_groups_json.{$index}", 'Each group must use a supported balancer strategy.');
                }
            }

            $routes = json_decode((string) $this->input('routes_json'), true);
            foreach (is_array($routes) ? $routes : [] as $index => $route) {
                if (! is_array($route) || ! in_array($route['target_type'] ?? null, ['direct', 'block', 'blocked', 'outbound', 'balancer'], true)) {
                    $validator->errors()->add("routes_json.{$index}", 'Each route must use a supported target type.');
                }
            }
        });
    }

    protected function dtoPayload(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'dns_settings_id' => isset($validated['dns_settings_id']) ? (int) $validated['dns_settings_id'] : null,
            'geodata_id' => isset($validated['geodata_id']) ? (int) $validated['geodata_id'] : null,
            'xray_inbound_ids' => $this->ids($validated['xray_inbound_ids'] ?? []),
            'external_subscription_config_ids' => $this->ids($validated['external_subscription_config_ids'] ?? []),
            'proxy_ids' => $this->ids($validated['proxy_ids'] ?? []),
            'xray_routing_ids' => $this->ids($validated['xray_routing_ids'] ?? []),
            'base_settings' => json_decode((string) $validated['base_settings_json'], true, 512, JSON_THROW_ON_ERROR),
            'outbound_groups' => json_decode((string) $validated['outbound_groups_json'], true, 512, JSON_THROW_ON_ERROR),
            'routes' => json_decode((string) $validated['routes_json'], true, 512, JSON_THROW_ON_ERROR),
            'is_active' => (bool) $validated['is_active'],
            'sort_order' => (int) $validated['sort_order'],
        ];
    }

    private function ids(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
