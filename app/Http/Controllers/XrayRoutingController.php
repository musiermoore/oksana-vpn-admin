<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\XrayRouting\XrayRoutingImportData;
use App\DTOs\XrayRouting\XrayRoutingData;
use App\Enums\XrayRoutingOutbound;
use App\Http\Requests\XrayRouting\ImportXrayRoutingSettingsRequest;
use App\Http\Requests\XrayRouting\UpdateXrayRoutingRequest;
use App\Models\XrayJsonSetting;
use App\Models\XrayRouting;
use App\Models\Server;
use App\Models\VlessExternalSubscription;
use App\Models\Proxy;
use App\Services\Subscriptions\RoscomVpnJsonSettingsImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class XrayRoutingController extends Controller
{
    public function index(Request $request)
    {
        $activeSettings = XrayJsonSetting::query()
            ->latestActive()
            ->first();

        $routings = XrayRouting::query()
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return $this->inertia('XrayRoutings/Index', [
            'import_url' => route('xray-routings.import'),
            'create_page_url' => route('xray-routings.create'),
            'active_settings' => $activeSettings ? [
                'id' => $activeSettings->id,
                'name' => $activeSettings->name,
                'source' => $activeSettings->source,
                'dns' => $activeSettings->dns,
                'routing' => $activeSettings->routing,
                'geodata' => $activeSettings->geodata,
                'imported_at' => $activeSettings->imported_at?->toDateTimeString(),
                'created_at' => $activeSettings->created_at?->toDateTimeString(),
            ] : null,
            'routings' => $routings->through(function (XrayRouting $routing): array {
                $outbound = $routing->outbound;

                return [
                    'id' => $routing->id,
                    'name' => $routing->name,
                    'description' => $routing->description,
                    'outbound' => $outbound instanceof XrayRoutingOutbound ? $outbound->value : (string) $outbound,
                    'subscription_types' => $routing->subscription_types,
                    'xray_inbound_ids' => $routing->xray_inbound_ids,
                    'external_subscription_config_ids' => $routing->external_subscription_config_ids,
                    'proxy_ids' => $routing->proxy_ids,
                    'is_global' => $routing->is_global,
                    'rules' => $routing->rules,
                    'sort_order' => $routing->sort_order,
                    'is_active' => $routing->is_active,
                    'links' => [
                        'edit' => route('xray-routings.edit', $routing),
                        'update' => route('xray-routings.update', $routing),
                    ],
                ];
            })->toArray(),
        ]);
    }

    public function create()
    {
        return $this->inertia('XrayRoutings/Create', [
            ...$this->formProps(),
            'submit_url' => route('xray-routings.store'),
            'routing' => [
                'sort_order' => XrayRouting::query()->count(),
            ],
        ]);
    }

    public function store(UpdateXrayRoutingRequest $request): RedirectResponse
    {
        /** @var XrayRoutingData $data */
        $data = $request->toDto();

        XrayRouting::query()->create([
            'name' => $data->name,
            'description' => $data->description,
            'source' => 'manual',
            'source_key' => null,
            'outbound' => $data->outbound,
            'subscription_types' => $data->subscription_types,
            'xray_inbound_ids' => $data->xray_inbound_ids,
            'external_subscription_config_ids' => $data->external_subscription_config_ids,
            'proxy_ids' => $data->proxy_ids,
            'is_global' => $data->is_global,
            'rules' => $data->rules,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);

        return redirect()
            ->route('xray-routings.index')
            ->with('success', 'Xray routing created.');
    }

    public function edit(XrayRouting $xrayRouting)
    {
        return $this->inertia('XrayRoutings/Edit', [
            ...$this->formProps(),
            'submit_url' => route('xray-routings.update', $xrayRouting),
            'routing' => $this->routingPayload($xrayRouting),
        ]);
    }

    public function import(
        ImportXrayRoutingSettingsRequest $request,
        RoscomVpnJsonSettingsImporter $importer
    ): RedirectResponse {
        /** @var XrayRoutingImportData $data */
        $data = $request->toDto();

        try {
            $importer->import($data->payload);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('xray-routings.index')
                ->withErrors(['settings_json' => $exception->getMessage()]);
        }

        return redirect()
            ->route('xray-routings.index')
            ->with('success', 'Xray routing settings imported.');
    }

    public function update(UpdateXrayRoutingRequest $request, XrayRouting $xrayRouting): RedirectResponse
    {
        /** @var XrayRoutingData $data */
        $data = $request->toDto();

        $xrayRouting->update([
            'name' => $data->name,
            'description' => $data->description,
            'outbound' => $data->outbound,
            'subscription_types' => $data->subscription_types,
            'xray_inbound_ids' => $data->xray_inbound_ids,
            'external_subscription_config_ids' => $data->external_subscription_config_ids,
            'proxy_ids' => $data->proxy_ids,
            'is_global' => $data->is_global,
            'rules' => $data->rules,
            'sort_order' => $data->sort_order,
            'is_active' => $data->is_active,
        ]);

        return redirect()
            ->route('xray-routings.index')
            ->with('success', 'Xray routing updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'index_url' => route('xray-routings.index'),
            'outbound_options' => collect(XrayRoutingOutbound::cases())
                ->map(fn (XrayRoutingOutbound $outbound): array => [
                    'label' => $outbound->value,
                    'value' => $outbound->value,
                ])
                ->values()
                ->all(),
            'subscription_type_options' => [
                [
                    'label' => 'Стандартная подписка (connect)',
                    'value' => XrayRouting::SUBSCRIPTION_CONNECT,
                ],
                [
                    'label' => 'Белые списки (connect-wl)',
                    'value' => XrayRouting::SUBSCRIPTION_CONNECT_WL,
                ],
            ],
            'target_tree' => [
                'servers' => $this->serverTargets(),
                'external_subscriptions' => $this->externalSubscriptionTargets(),
                'proxies' => $this->proxyTargets(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routingPayload(XrayRouting $routing): array
    {
        $outbound = $routing->outbound;

        return [
            'id' => $routing->id,
            'name' => $routing->name,
            'description' => $routing->description,
            'outbound' => $outbound instanceof XrayRoutingOutbound ? $outbound->value : (string) $outbound,
            'subscription_types' => $routing->subscription_types,
            'xray_inbound_ids' => $routing->xray_inbound_ids,
            'external_subscription_config_ids' => $routing->external_subscription_config_ids,
            'proxy_ids' => $routing->proxy_ids,
            'is_global' => $routing->is_global,
            'rules' => $routing->rules,
            'sort_order' => $routing->sort_order,
            'is_active' => $routing->is_active,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serverTargets(): array
    {
        return Server::query()
            ->whereHas('xrayInbounds')
            ->with([
                'xrayInbounds' => fn ($query) => $query
                    ->select('id', 'server_id', 'external_id', 'sort_order', 'is_active', 'is_public')
                    ->ordered(),
            ])
            ->ordered()
            ->get()
            ->map(fn (Server $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'inbounds' => $server->xrayInbounds
                    ->map(fn ($inbound): array => [
                        'id' => $inbound->id,
                        'external_id' => $inbound->external_id,
                        'label' => 'Inbound #'.$inbound->external_id,
                        'is_active' => $inbound->is_active,
                        'is_public' => $inbound->is_public,
                    ])
                    ->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function externalSubscriptionTargets(): array
    {
        return VlessExternalSubscription::query()
            ->whereHas('configs')
            ->with('configs')
            ->ordered()
            ->get()
            ->map(fn (VlessExternalSubscription $subscription): array => [
                'id' => $subscription->id,
                'name' => $subscription->name,
                'configs' => $subscription->configs
                    ->map(fn ($config): array => [
                        'id' => $config->id,
                        'name' => $config->name,
                        'protocol' => $config->protocol,
                    ])
                    ->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,name:string,server_name:string|null,inbound_id:int|null}>
     */
    private function proxyTargets(): array
    {
        return Proxy::query()
            ->with('server:id,name')
            ->orderBy('server_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Proxy $proxy): array => [
                'id' => (int) $proxy->id,
                'name' => (string) $proxy->name,
                'server_name' => $proxy->server?->name,
                'inbound_id' => $proxy->inbound_id,
            ])
            ->values()
            ->all();
    }
}
