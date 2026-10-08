<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\XrayGlobalConfig\XrayGlobalConfigData;
use App\Http\Requests\XrayGlobalConfig\UpdateXrayGlobalConfigRequest;
use App\Models\XrayJsonSetting;
use App\Models\XrayRouting;
use App\Models\XrayRoutingDnsSettings;
use App\Services\Subscriptions\ConnectJsonProfileSettingsProvider;
use Illuminate\Http\RedirectResponse;

class XrayGlobalConfigController extends Controller
{
    public function edit(ConnectJsonProfileSettingsProvider $provider)
    {
        $settings = XrayJsonSetting::query()
            ->where('source', 'manual_global')
            ->latest('id')
            ->first();

        return $this->inertia('XrayGlobalConfig/Form', [
            'submit_url' => route('xray-global-config.update'),
            'config' => [
                'dns' => $settings?->dns ?? $provider->dns(),
                'routing' => $settings?->routing ?? [
                    'domainStrategy' => data_get($provider->routing(), 'domainStrategy', 'AsIs'),
                ],
                'rules' => XrayRouting::query()->where('source', 'manual_global')->active()->ordered()->get()->map(
                    fn (XrayRouting $routing): array => $routing->toXrayRule('direct', 'proxy', 'block')
                )->values()->all(),
            ],
        ]);
    }

    public function update(UpdateXrayGlobalConfigRequest $request): RedirectResponse
    {
        /** @var XrayGlobalConfigData $data */
        $data = $request->toDto();
        $dnsSettings = XrayRoutingDnsSettings::query()->firstOrNew(['name' => 'Global JSON DNS']);
        $dnsSettings->fill([
            'description' => 'Used by standard JSON configurations.',
            'servers' => $data->dns['servers'] ?? [],
            'settings' => $data->dns,
            'query_strategy' => $data->dns['queryStrategy'] ?? 'UseIPv4',
            'enable_parallel_query' => (bool) ($data->dns['enableParallelQuery'] ?? false),
            'is_active' => true,
            'is_default' => true,
        ])->save();
        XrayRoutingDnsSettings::query()->where('id', '!=', $dnsSettings->id)->update(['is_default' => false]);

        $settings = XrayJsonSetting::query()->firstOrNew(['source' => 'manual_global']);
        $settings->fill([
            'name' => 'Global JSON configuration',
            'description' => 'Managed through the global Xray JSON settings page.',
            'dns' => $data->dns,
            'routing' => $data->routing,
            'raw' => ['rules' => $data->rules],
            'is_active' => true,
            'imported_at' => null,
        ])->save();

        XrayRouting::query()->where('source', 'manual_global')->delete();
        $timestamp = now();
        $routingRows = [];

        foreach ($data->rules as $index => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $outbound = match ((string) ($rule['outboundTag'] ?? 'proxy')) {
                'direct' => 'direct',
                'block', 'blocked' => 'blocked',
                default => 'proxy',
            };
            unset($rule['type'], $rule['outboundTag']);

            $routingRows[] = [
                'name' => 'Global JSON rule #'.((int) $index + 1),
                'description' => 'Managed by Global JSON configuration.',
                'source' => 'manual_global',
                'source_key' => (string) $index,
                'outbound' => $outbound,
                'subscription_types' => json_encode(['connect', 'connect_wl'], JSON_THROW_ON_ERROR),
                'xray_inbound_ids' => json_encode([], JSON_THROW_ON_ERROR),
                'external_subscription_config_ids' => json_encode([], JSON_THROW_ON_ERROR),
                'proxy_ids' => json_encode([], JSON_THROW_ON_ERROR),
                'is_global' => true,
                'rules' => json_encode($rule, JSON_THROW_ON_ERROR),
                'sort_order' => (int) $index,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($routingRows !== []) {
            XrayRouting::query()->insert($routingRows);
        }

        return redirect()->back()->with('success', 'Global Xray JSON configuration updated.');
    }
}
