<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\XrayCustomConfig\XrayCustomConfigData;
use App\Http\Requests\XrayCustomConfig\StoreXrayDnsSettingsRequest;
use App\Http\Requests\XrayCustomConfig\UpdateXrayCustomConfigRequest;
use App\Models\Proxy;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessExternalSubscription;
use App\Models\VlessExternalSubscriptionConfig;
use App\Models\XrayCustomConfig;
use App\Models\XrayCustomConfigOutboundGroup;
use App\Models\XrayCustomConfigRoute;
use App\Models\XrayRouting;
use App\Models\XrayRoutingDnsSettings;
use App\Models\XrayRoutingGeodata;
use App\Services\SubscriptionMetadataService;
use App\Services\Subscriptions\XrayCustomConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class XrayCustomConfigController extends Controller
{
    public function index()
    {
        return $this->inertia('XrayCustomConfigs/Index', [
            'create_page_url' => route('xray-custom-configs.create'),
            'configs' => XrayCustomConfig::query()->with(['dnsSettings', 'geodata'])->latest('id')->get()->map(
                fn (XrayCustomConfig $config): array => $this->payload($config)
            )->values(),
        ]);
    }

    public function create()
    {
        return $this->inertia('XrayCustomConfigs/Form', [
            ...$this->formProps(),
            'mode' => 'create',
            'submit_url' => route('xray-custom-configs.store'),
            'method' => 'post',
            'config' => ['sort_order' => (int) XrayCustomConfig::query()->max('sort_order') + 1],
            'create_dns_url' => route('xray-dns-settings.store'),
            'create_geodata_url' => route('xray-geodata.store'),
        ]);
    }

    public function store(UpdateXrayCustomConfigRequest $request): RedirectResponse
    {
        /** @var XrayCustomConfigData $data */
        $data = $request->toDto();
        $config = XrayCustomConfig::query()->create($data->toArray());
        $this->syncGroupsAndRoutes($config, $data);

        return redirect()->route('xray-custom-configs.edit', $config)->with('success', 'Xray custom config created.');
    }

    public function edit(XrayCustomConfig $xrayCustomConfig)
    {
        $xrayCustomConfig->load(['outboundGroups.fallbackGroup', 'routes']);

        return $this->inertia('XrayCustomConfigs/Form', [
            ...$this->formProps(),
            'mode' => 'edit',
            'submit_url' => route('xray-custom-configs.update', $xrayCustomConfig),
            'method' => 'put',
            'config' => $this->payload($xrayCustomConfig),
            'create_dns_url' => route('xray-dns-settings.store'),
            'create_geodata_url' => route('xray-geodata.store'),
        ]);
    }

    public function update(UpdateXrayCustomConfigRequest $request, XrayCustomConfig $xrayCustomConfig): RedirectResponse
    {
        /** @var XrayCustomConfigData $data */
        $data = $request->toDto();
        $xrayCustomConfig->update($data->toArray());
        $this->syncGroupsAndRoutes($xrayCustomConfig, $data);

        return redirect()->back()->with('success', 'Xray custom config updated.');
    }

    public function preview(
        Request $request,
        XrayCustomConfig $xrayCustomConfig,
        XrayCustomConfigService $service,
    ): JsonResponse {
        $user = $this->previewUser($request);

        $result = $service->build($user, $xrayCustomConfig);

        return response()->json([
            'user_id' => $user->id,
            'content' => json_decode($result->content, true),
        ]);
    }

    public function previewDraft(
        UpdateXrayCustomConfigRequest $request,
        XrayCustomConfigService $service,
    ): JsonResponse {
        /** @var XrayCustomConfigData $data */
        $data = $request->toDto();
        $config = new XrayCustomConfig($data->toArray());
        $this->setDraftGroupsAndRoutes($config, $data);
        $config->load(['dnsSettings', 'geodata']);

        $user = $this->previewUser($request);

        $result = $service->build($user, $config);

        return response()->json([
            'user_id' => $user->id,
            'content' => json_decode($result->content, true),
        ]);
    }

    public function connect(
        Request $request,
        XrayCustomConfig $xrayCustomConfig,
        XrayCustomConfigService $service,
        SubscriptionMetadataService $metadataService,
    ) {
        if (! $xrayCustomConfig->is_active) {
            abort(404);
        }

        $credentials = $this->credentials($request);
        $user = $credentials === null
            ? null
            : User::query()->whereTelegramId($credentials['tg'])->find($credentials['id']);

        if ($user === null) {
            return null;
        }

        $result = $service->build($user, $xrayCustomConfig);
        $response = response($request->boolean('base64') ? base64_encode($result->content) : $result->content);

        foreach ($metadataService->buildHeaders(
            $user,
            $result->fileExtension,
            $result->contentType,
            (string) $xrayCustomConfig->name,
            true,
            true,
            true,
            '1',
        ) as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    public function storeDnsSettings(StoreXrayDnsSettingsRequest $request): JsonResponse
    {
        $data = $request->toDto();

        $settings = XrayRoutingDnsSettings::query()->create([
            ...$data->toModelAttributes(),
            'is_active' => true,
        ]);

        if ($settings->is_default) {
            XrayRoutingDnsSettings::query()
                ->where('id', '!=', $settings->id)
                ->update(['is_default' => false]);
        }

        return response()->json(['resource' => ['id' => $settings->id, 'name' => $settings->name]]);
    }

    public function storeGeodata(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'geoip_url' => ['nullable', 'url', 'max:2000'],
            'geosite_url' => ['nullable', 'url', 'max:2000'],
        ]);

        if (($data['geoip_url'] ?? '') === '' && ($data['geosite_url'] ?? '') === '') {
            return response()->json(['message' => 'Укажите хотя бы один URL geodata.'], 422);
        }

        $assets = array_values(array_filter([
            ! empty($data['geoip_url']) ? ['url' => $data['geoip_url'], 'file' => 'geoip.dat'] : null,
            ! empty($data['geosite_url']) ? ['url' => $data['geosite_url'], 'file' => 'geosite.dat'] : null,
        ]));

        $geodata = XrayRoutingGeodata::query()->create([
            ...$data,
            'assets' => $assets,
            'is_active' => true,
        ]);

        return response()->json(['resource' => ['id' => $geodata->id, 'name' => $geodata->name]]);
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        return [
            'dns_settings' => XrayRoutingDnsSettings::query()->active()->latest('id')->get(['id', 'name']),
            'geodata' => XrayRoutingGeodata::query()->active()->latest('id')->get(['id', 'name']),
            'users' => User::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'name', 'telegram', 'telegram_id'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'telegram_id' => $user->telegram_id,
                ])
                ->values(),
            'targets' => [
                'servers' => Server::query()->whereHas('xrayInbounds')->with('xrayInbounds:id,server_id,external_id')->ordered()->get(),
                'external_subscriptions' => VlessExternalSubscription::query()->whereHas('configs')->with('configs')->ordered()->get(),
                'proxies' => Proxy::query()->with('server:id,name')->orderBy('server_id')->orderBy('sort_order')->get(),
                'routings' => XrayRouting::query()->ordered()->get(['id', 'name', 'description', 'is_active']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function payload(XrayCustomConfig $config): array
    {
        return [
            'id' => $config->id,
            'name' => $config->name,
            'slug' => $config->slug,
            'description' => $config->description,
            'dns_settings_id' => $config->dns_settings_id,
            'geodata_id' => $config->geodata_id,
            'xray_inbound_ids' => $config->xray_inbound_ids ?? [],
            'external_subscription_config_ids' => $config->external_subscription_config_ids ?? [],
            'external_subscription_ids' => $config->external_subscription_ids
                ?? $this->subscriptionIdsForConfigIds($config->external_subscription_config_ids ?? []),
            'proxy_ids' => $config->proxy_ids ?? [],
            'xray_routing_ids' => $config->xray_routing_ids ?? [],
            'base_settings' => $config->base_settings ?? [],
            'outbound_groups' => $config->relationLoaded('outboundGroups')
                ? $config->outboundGroups->map(fn ($group): array => [
                    'name' => $group->name,
                    'tag' => $group->tag,
                    'strategy' => $group->strategy?->value ?? (string) $group->strategy,
                    'fallback_group_tag' => $group->fallbackGroup?->tag,
                    'xray_inbound_ids' => $group->xray_inbound_ids ?? [],
                    'external_subscription_config_ids' => $group->external_subscription_config_ids ?? [],
                    'external_subscription_ids' => $group->external_subscription_ids
                        ?? $this->subscriptionIdsForConfigIds($group->external_subscription_config_ids ?? []),
                    'proxy_ids' => $group->proxy_ids ?? [],
                    'strategy_settings' => $group->strategy_settings ?? [],
                    'sort_order' => $group->sort_order,
                    'is_active' => $group->is_active,
                ])->values()->all()
                : [],
            'routes' => $config->relationLoaded('routes')
                ? $config->routes->map(fn ($route): array => [
                    'name' => $route->name,
                    'rules' => $route->rules,
                    'target_type' => $route->target_type,
                    'target_tag' => $route->target_tag,
                    'sort_order' => $route->sort_order,
                    'is_active' => $route->is_active,
                ])->values()->all()
                : [],
            'is_active' => $config->is_active,
            'sort_order' => $config->sort_order,
            'dns_settings' => $config->relationLoaded('dnsSettings') && $config->dnsSettings !== null
                ? ['id' => $config->dnsSettings->id, 'name' => $config->dnsSettings->name]
                : null,
            'geodata' => $config->relationLoaded('geodata') && $config->geodata !== null
                ? ['id' => $config->geodata->id, 'name' => $config->geodata->name]
                : null,
            'connect_url' => route('vless.custom-connect', $config),
            'links' => ['edit' => route('xray-custom-configs.edit', $config)],
        ];
    }

    /** @return array{tg:string,id:int}|null */
    private function credentials(Request $request): ?array
    {
        $tg = $request->query('tg');
        $id = $request->query('i');

        if (! is_string($tg) || ! is_string($id) || $tg === '' || $id === '') {
            return null;
        }

        try {
            return ['tg' => (string) Crypt::decryptString($tg), 'id' => (int) Crypt::decryptString($id)];
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function previewUser(Request $request): User
    {
        if ($request->input('preview_mode', 'admin') === 'admin') {
            return User::query()
                ->where('is_admin', true)
                ->where('is_active', true)
                ->orderBy('id')
                ->firstOrFail();
        }

        return User::query()
            ->where('is_active', true)
            ->findOrFail($request->integer('user_id'));
    }

    private function syncGroupsAndRoutes(XrayCustomConfig $config, XrayCustomConfigData $data): void
    {
        $config->outboundGroups()->delete();
        $config->routes()->delete();
        $groupsByTag = [];

        foreach ($data->outbound_groups as $index => $group) {
            $created = $config->outboundGroups()->create([
                'name' => (string) ($group['name'] ?? 'Group '.($index + 1)),
                'tag' => (string) ($group['tag'] ?? 'group-'.($index + 1)),
                'strategy' => (string) ($group['strategy'] ?? 'roundRobin'),
                'xray_inbound_ids' => $group['xray_inbound_ids'] ?? [],
                'external_subscription_config_ids' => $group['external_subscription_config_ids'] ?? [],
                'external_subscription_ids' => $group['external_subscription_ids'] ?? [],
                'proxy_ids' => $group['proxy_ids'] ?? [],
                'strategy_settings' => $group['strategy_settings'] ?? [],
                'sort_order' => (int) ($group['sort_order'] ?? $index),
                'is_active' => (bool) ($group['is_active'] ?? true),
            ]);
            $groupsByTag[(string) $created->tag] = $created;
        }

        foreach ($data->outbound_groups as $index => $group) {
            $created = $groupsByTag[(string) ($group['tag'] ?? 'group-'.($index + 1))] ?? null;
            $fallback = $groupsByTag[(string) ($group['fallback_group_tag'] ?? '')] ?? null;
            if ($created !== null && $fallback !== null) {
                $created->update(['fallback_group_id' => $fallback->id]);
            }
        }

        foreach ($data->routes as $index => $route) {
            $config->routes()->create([
                'name' => (string) ($route['name'] ?? 'Route '.($index + 1)),
                'rules' => is_array($route['rules'] ?? null) ? $route['rules'] : [],
                'target_type' => (string) ($route['target_type'] ?? 'direct'),
                'target_tag' => isset($route['target_tag']) ? (string) $route['target_tag'] : null,
                'sort_order' => (int) ($route['sort_order'] ?? $index),
                'is_active' => (bool) ($route['is_active'] ?? true),
            ]);
        }
    }

    private function setDraftGroupsAndRoutes(XrayCustomConfig $config, XrayCustomConfigData $data): void
    {
        $groups = [];
        $groupsByTag = [];

        foreach ($data->outbound_groups as $index => $group) {
            $model = new XrayCustomConfigOutboundGroup([
                'name' => (string) ($group['name'] ?? 'Group '.($index + 1)),
                'tag' => (string) ($group['tag'] ?? 'group-'.($index + 1)),
                'strategy' => (string) ($group['strategy'] ?? 'roundRobin'),
                'xray_inbound_ids' => $group['xray_inbound_ids'] ?? [],
                'external_subscription_config_ids' => $group['external_subscription_config_ids'] ?? [],
                'external_subscription_ids' => $group['external_subscription_ids'] ?? [],
                'proxy_ids' => $group['proxy_ids'] ?? [],
                'strategy_settings' => $group['strategy_settings'] ?? [],
                'sort_order' => (int) ($group['sort_order'] ?? $index),
                'is_active' => (bool) ($group['is_active'] ?? true),
            ]);
            $groups[] = $model;
            $groupsByTag[(string) $model->tag] = $model;
        }

        foreach ($data->outbound_groups as $index => $group) {
            $model = $groupsByTag[(string) ($group['tag'] ?? 'group-'.($index + 1))] ?? null;
            $fallback = $groupsByTag[(string) ($group['fallback_group_tag'] ?? '')] ?? null;
            if ($model !== null) {
                $model->setRelation('fallbackGroup', $fallback);
            }
        }

        $routes = [];
        foreach ($data->routes as $index => $route) {
            $routes[] = new XrayCustomConfigRoute([
                'name' => (string) ($route['name'] ?? 'Route '.($index + 1)),
                'rules' => is_array($route['rules'] ?? null) ? $route['rules'] : [],
                'target_type' => (string) ($route['target_type'] ?? 'direct'),
                'target_tag' => isset($route['target_tag']) ? (string) $route['target_tag'] : null,
                'sort_order' => (int) ($route['sort_order'] ?? $index),
                'is_active' => (bool) ($route['is_active'] ?? true),
            ]);
        }

        $config->setRelation('outboundGroups', collect($groups)->sortBy('sort_order')->values());
        $config->setRelation('routes', collect($routes)->sortBy('sort_order')->values());
    }

    /** @param array<int, int|string> $configIds */
    private function subscriptionIdsForConfigIds(array $configIds): array
    {
        if ($configIds === []) {
            return [];
        }

        return VlessExternalSubscriptionConfig::query()
            ->whereIn('id', array_map('intval', $configIds))
            ->pluck('vless_external_subscription_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
