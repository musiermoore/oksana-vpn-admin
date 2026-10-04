<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Builders;

use App\DTOs\Subscription\NormalizedNode;
use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Models\XrayRouting;
use App\Models\XrayCustomConfig;
use App\Services\Subscriptions\ConnectJsonProfileSettingsProvider;
use App\Services\Subscriptions\SubscriptionUriParser;
use App\Services\Subscriptions\XrayJsonProfileNormalizer;

class ConnectJsonBuilder implements SubscriptionBuilder
{
    public function __construct(
        private readonly SubscriptionUriParser $parser,
        private readonly ConnectJsonProfileSettingsProvider $settingsProvider,
        private readonly XrayJsonProfileNormalizer $profileNormalizer,
    ) {}

    /**
     * @param  array<int, NormalizedNode>  $nodes
     */
    public function build(array $nodes): SubscriptionBuildResult
    {
        return $this->buildForSubscriptionType($nodes, XrayRouting::SUBSCRIPTION_CONNECT);
    }

    /**
     * @param  array<int, NormalizedNode>  $nodes
     */
    public function buildForSubscriptionType(array $nodes, string $subscriptionType): SubscriptionBuildResult
    {
        $profiles = collect($nodes)
            ->map(fn (NormalizedNode $node) => $this->buildProfile($node, $subscriptionType))
            ->filter()
            ->map(fn (array $profile): array => $this->profileNormalizer->normalizeProfile($profile))
            ->values()
            ->all();

        return new SubscriptionBuildResult(
            content: json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]',
            contentType: 'application/json; charset=UTF-8',
            fileExtension: 'json',
        );
    }

    /**
     * @param  array<int, NormalizedNode>  $nodes
     */
    public function buildForCustomConfig(array $nodes, XrayCustomConfig $customConfig): SubscriptionBuildResult
    {
        if ($customConfig->relationLoaded('outboundGroups') && $customConfig->outboundGroups->isNotEmpty()) {
            return $this->buildGroupedCustomConfig($nodes, $customConfig);
        }

        $selectedInboundIds = array_map('intval', $customConfig->xray_inbound_ids ?? []);
        $selectedExternalIds = array_map('intval', $customConfig->external_subscription_config_ids ?? []);
        $selectedProxyIds = array_map('intval', $customConfig->proxy_ids ?? []);

        $nodes = array_values(array_filter($nodes, function (NormalizedNode $node) use (
            $selectedInboundIds,
            $selectedExternalIds,
            $selectedProxyIds,
        ): bool {
            $inboundId = isset($node->meta['xray_inbound_id']) ? (int) $node->meta['xray_inbound_id'] : null;
            $externalId = isset($node->meta['external_subscription_config_id'])
                ? (int) $node->meta['external_subscription_config_id']
                : null;
            $proxyId = isset($node->meta['proxy_id']) ? (int) $node->meta['proxy_id'] : null;

            return ($inboundId !== null && in_array($inboundId, $selectedInboundIds, true))
                || ($externalId !== null && in_array($externalId, $selectedExternalIds, true))
                || ($proxyId !== null && in_array($proxyId, $selectedProxyIds, true));
        }));

        $profiles = collect($nodes)
            ->map(fn (NormalizedNode $node) => $this->buildProfile($node, XrayRouting::SUBSCRIPTION_CONNECT, $customConfig))
            ->filter()
            ->map(fn (array $profile): array => $this->profileNormalizer->normalizeProfile($profile))
            ->values()
            ->all();

        return new SubscriptionBuildResult(
            content: json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]',
            contentType: 'application/json; charset=UTF-8',
            fileExtension: 'json',
        );
    }

    /**
     * Builds one combined Xray profile with user-specific outbounds and balancers.
     *
     * @param  array<int, NormalizedNode>  $nodes
     */
    private function buildGroupedCustomConfig(array $nodes, XrayCustomConfig $customConfig): SubscriptionBuildResult
    {
        $groups = $customConfig->outboundGroups->where('is_active', true)->values();
        $outbounds = [];
        $groupTags = [];

        foreach ($groups as $group) {
            $groupTags[(int) $group->id] = (string) $group->tag;
            $groupNodes = $this->filterNodesForTargets($nodes, $group->xray_inbound_ids, $group->external_subscription_config_ids, $group->proxy_ids);

            foreach ($groupNodes as $index => $node) {
                $outbound = $this->buildOutboundForNode($node);

                if ($outbound === null) {
                    continue;
                }

                $outbound['tag'] = (string) $group->tag.'-'.($index + 1);
                $outbounds[] = $outbound;
            }
        }

        $balancers = [];
        $fallbackLoopRules = [];
        $loopbackOutbounds = [];

        foreach ($groups as $group) {
            $fallback = $group->fallbackGroup;
            // Xray expects balancer strategy settings to be a JSON object,
            // including when the strategy has no options.
            $strategySettings = (object) (is_array($group->strategy_settings)
                ? $group->strategy_settings ?: []
                : []);
            $balancer = [
                'tag' => (string) $group->tag,
                'selector' => [(string) $group->tag.'-'],
                'strategy' => [
                    'type' => $group->strategy?->value ?? (string) $group->strategy,
                    'settings' => $strategySettings ?: (object) [],
                ],
            ];

            if ($fallback !== null && $fallback->is_active) {
                $loopInbound = (string) $group->tag.'-fallback-in';
                $loopTag = (string) $group->tag.'-fallback-loop';
                $balancer['fallbackTag'] = $loopTag;
                $loopbackOutbounds[] = [
                    'protocol' => 'loopback',
                    'tag' => $loopTag,
                    'settings' => ['inboundTag' => $loopInbound],
                ];
                $fallbackLoopRules[] = [
                    'type' => 'field',
                    'inboundTag' => [$loopInbound],
                    'balancerTag' => (string) $fallback->tag,
                ];
            }

            $balancers[] = $balancer;
        }

        $routingRules = $fallbackLoopRules;

        foreach ($customConfig->routes->where('is_active', true) as $route) {
            $rule = is_array($route->rules) ? $route->rules : [];
            $target = match ((string) $route->target_type) {
                'direct' => ['outboundTag' => $this->settingsProvider->directTag()],
                'block', 'blocked' => ['outboundTag' => $this->settingsProvider->blockTag()],
                'outbound' => ['outboundTag' => (string) $route->target_tag],
                default => ['balancerTag' => (string) $route->target_tag],
            };
            $routingRules[] = ['type' => 'field', ...$rule, ...$target];
        }

        // A custom route table is intentionally open-ended, but traffic not
        // matched by one of its rules must remain usable without the VPN.
        $routingRules[] = [
            'type' => 'field',
            'network' => 'tcp,udp',
            'outboundTag' => $this->settingsProvider->directTag(),
        ];

        $routing = [
            ...(is_array($customConfig->base_settings['routing'] ?? null) ? $customConfig->base_settings['routing'] : []),
            'domainStrategy' => (string) data_get($customConfig->base_settings, 'routing.domainStrategy', 'AsIs'),
            'rules' => $routingRules,
            'balancers' => $balancers,
        ];

        $base = $customConfig->base_settings ?? [];
        $profile = [
            ...$base,
            'remarks' => (string) $customConfig->name,
            'log' => $this->settingsProvider->log(),
            'dns' => $this->settingsProvider->dnsFromSettings($customConfig->dnsSettings),
            'routing' => $routing,
            'inbounds' => $base['inbounds'] ?? $this->settingsProvider->inbounds(),
            'outbounds' => [
                ...$outbounds,
                ...$loopbackOutbounds,
                $this->settingsProvider->directOutbound(),
                $this->settingsProvider->blockOutbound(),
            ],
        ];

        $geodata = $this->settingsProvider->geodataFromSettings($customConfig->geodata);
        if ($geodata !== null) {
            $profile['geodata'] = $geodata;
        }

dd(json_encode([$this->profileNormalizer->normalizeProfile($profile)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        
        return new SubscriptionBuildResult(
            content: json_encode([$this->profileNormalizer->normalizeProfile($profile)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]',
            contentType: 'application/json; charset=UTF-8',
            fileExtension: 'json',
        );
    }

    /** @return array<int, NormalizedNode> */
    private function filterNodesForTargets(array $nodes, ?array $inboundIds, ?array $externalIds, ?array $proxyIds): array
    {
        $inboundIds = array_map('intval', $inboundIds ?? []);
        $externalIds = array_map('intval', $externalIds ?? []);
        $proxyIds = array_map('intval', $proxyIds ?? []);

        return array_values(array_filter($nodes, function (NormalizedNode $node) use ($inboundIds, $externalIds, $proxyIds): bool {
            $inbound = isset($node->meta['xray_inbound_id']) ? (int) $node->meta['xray_inbound_id'] : null;
            $external = isset($node->meta['external_subscription_config_id']) ? (int) $node->meta['external_subscription_config_id'] : null;
            $proxy = isset($node->meta['proxy_id']) ? (int) $node->meta['proxy_id'] : null;

            return ($inbound !== null && in_array($inbound, $inboundIds, true))
                || ($external !== null && in_array($external, $externalIds, true))
                || ($proxy !== null && in_array($proxy, $proxyIds, true));
        }));
    }

    /** @return array<string, mixed>|null */
    public function buildOutboundForNode(NormalizedNode $node): ?array
    {
        $parsed = $this->parser->parse($node->uri);

        if (! is_array($parsed)) {
            return null;
        }

        $outbound = match ($parsed['protocol']) {
            'vless' => $this->buildVlessOutbound($parsed),
            'trojan' => $this->buildTrojanOutbound($parsed),
            'shadowsocks' => $this->buildShadowsocksOutbound($parsed),
            'hysteria2' => $this->buildHysteria2Outbound($parsed),
            'hysteria' => $this->buildHysteriaOutbound($parsed),
            'wireguard', 'amneziawg' => $this->buildWireGuardOutbound($parsed),
            default => null,
        };

        return $outbound;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildProfile(
        NormalizedNode $node,
        string $subscriptionType,
        ?XrayCustomConfig $customConfig = null,
    ): ?array
    {
        $xrayInboundId = isset($node->meta['xray_inbound_id']) ? (int) $node->meta['xray_inbound_id'] : null;
        $externalSubscriptionConfigId = isset($node->meta['external_subscription_config_id'])
            ? (int) $node->meta['external_subscription_config_id']
            : null;
        $proxyId = isset($node->meta['proxy_id']) ? (int) $node->meta['proxy_id'] : null;

        if (is_array($node->meta['json_profile'] ?? null)) {
            $profile = [
                ...$node->meta['json_profile'],
                'remarks' => (string) ($node->meta['name'] ?? $node->serverName),
            ];

            if ($customConfig === null && $this->settingsProvider->hasTargetedRoutingRules(
                $subscriptionType,
                $xrayInboundId,
                $externalSubscriptionConfigId,
                $proxyId,
            )) {
                $profile['routing'] = $this->settingsProvider->routing(
                    $subscriptionType,
                    $xrayInboundId,
                    $externalSubscriptionConfigId,
                    $proxyId,
                );
            }

            if ($customConfig !== null) {
                $profile['dns'] = $this->settingsProvider->dnsFromSettings($customConfig->dnsSettings);
                $profile['routing'] = $this->customRouting($customConfig, $subscriptionType, $node);
                $profile = [...($customConfig->base_settings ?? []), ...$profile];
            }

            return $profile;
        }

        $parsed = $this->parser->parse($node->uri);

        if (! is_array($parsed)) {
            return null;
        }

        $proxyOutbound = match ($parsed['protocol']) {
            'vless' => $this->buildVlessOutbound($parsed),
            'trojan' => $this->buildTrojanOutbound($parsed),
            'shadowsocks' => $this->buildShadowsocksOutbound($parsed),
            'hysteria2' => $this->buildHysteria2Outbound($parsed),
            'hysteria' => $this->buildHysteriaOutbound($parsed),
            'wireguard', 'amneziawg' => $this->buildWireGuardOutbound($parsed),
            default => null,
        };

        if ($proxyOutbound === null) {
            return null;
        }

        $proxyOutbound['tag'] = $this->settingsProvider->proxyTag();

        $profile = [
            ...($customConfig?->base_settings ?? []),
            'remarks' => (string) ($node->meta['name'] ?? $node->serverName),
            'log' => $this->settingsProvider->log(),
            'dns' => $customConfig === null
                ? $this->settingsProvider->dns()
                : $this->settingsProvider->dnsFromSettings($customConfig->dnsSettings),
            ...array_filter([
                'geodata' => $customConfig === null
                    ? $this->settingsProvider->geodata()
                    : $this->settingsProvider->geodataFromSettings($customConfig->geodata),
            ]),
            'routing' => $customConfig === null
                ? $this->settingsProvider->routing($subscriptionType, $xrayInboundId, $externalSubscriptionConfigId, $proxyId)
                : $this->customRouting($customConfig, $subscriptionType, $node),
            'inbounds' => $this->settingsProvider->inbounds(),
            'outbounds' => [
                $proxyOutbound,
                $this->settingsProvider->directOutbound(),
                $this->settingsProvider->blockOutbound(),
            ],
        ];

        return $profile;
    }

    private function customRouting(
        XrayCustomConfig $customConfig,
        string $subscriptionType,
        NormalizedNode $node,
    ): array {
        $rules = $this->settingsProvider->customRoutingRules(
            array_map('intval', $customConfig->xray_routing_ids ?? []),
            $subscriptionType,
            isset($node->meta['xray_inbound_id']) ? (int) $node->meta['xray_inbound_id'] : null,
            isset($node->meta['external_subscription_config_id'])
                ? (int) $node->meta['external_subscription_config_id']
                : null,
            isset($node->meta['proxy_id']) ? (int) $node->meta['proxy_id'] : null,
        );

        return [
            'domainStrategy' => (string) data_get($customConfig->base_settings, 'routing.domainStrategy', 'AsIs'),
            'rules' => $rules,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildVlessOutbound(array $parsed): array
    {
        $outbound = [
            'protocol' => 'vless',
            'settings' => [
                'vnext' => [[
                    'address' => $parsed['server'],
                    'port' => $parsed['port'],
                    'users' => [[
                        'id' => $parsed['uuid'],
                        'encryption' => $parsed['encryption'] !== '' ? $parsed['encryption'] : 'none',
                        'level' => 8,
                    ]],
                ]],
            ],
        ];

        if ($parsed['flow'] !== '') {
            $outbound['settings']['vnext'][0]['users'][0]['flow'] = $parsed['flow'];
        }

        $streamSettings = [
            'network' => $this->normalizeVlessNetwork((string) $parsed['transport']),
            'security' => $parsed['security'] !== '' ? $parsed['security'] : 'none',
        ];

        if ($parsed['security'] === 'tls') {
            $streamSettings['tlsSettings'] = array_filter([
                'serverName' => $parsed['sni'] !== '' ? $parsed['sni'] : null,
                'fingerprint' => $parsed['fp'] !== '' ? $parsed['fp'] : null,
                'alpn' => ($parsed['alpn'] ?? []) !== [] ? $parsed['alpn'] : null,
                'allowInsecure' => false,
            ], fn (mixed $value) => $value !== null);
        }

        if ($parsed['security'] === 'reality') {
            $streamSettings['realitySettings'] = array_filter([
                'show' => false,
                'serverName' => $parsed['sni'] !== '' ? $parsed['sni'] : null,
                'fingerprint' => $parsed['fp'] !== '' ? $parsed['fp'] : null,
                'publicKey' => $parsed['pbk'] !== '' ? $parsed['pbk'] : null,
                'shortId' => $parsed['sid'] !== '' ? $parsed['sid'] : null,
                'spiderX' => $parsed['spx'] !== '' ? $parsed['spx'] : '/',
            ], fn (mixed $value) => $value !== null);
        }

        $transportSettings = $this->buildTransportSettings($parsed);

        if ($transportSettings !== []) {
            $streamSettings = [...$streamSettings, ...$transportSettings];
        }

        $outbound['streamSettings'] = $streamSettings;

        return $outbound;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildTrojanOutbound(array $parsed): array
    {
        $outbound = [
            'protocol' => 'trojan',
            'settings' => [
                'servers' => [[
                    'address' => $parsed['server'],
                    'port' => $parsed['port'],
                    'password' => $parsed['password'],
                    'level' => 8,
                ]],
            ],
        ];

        $streamSettings = [
            'network' => $this->normalizeVlessNetwork((string) $parsed['transport']),
            'security' => $parsed['security'] !== '' ? $parsed['security'] : 'tls',
        ];

        if ($parsed['security'] !== 'none') {
            $streamSettings['tlsSettings'] = array_filter([
                'serverName' => $parsed['sni'] !== '' ? $parsed['sni'] : null,
                'alpn' => ($parsed['alpn'] ?? []) !== [] ? $parsed['alpn'] : null,
                'allowInsecure' => false,
            ], fn (mixed $value) => $value !== null);
        }

        $transportSettings = $this->buildTransportSettings($parsed);

        if ($transportSettings !== []) {
            $streamSettings = [...$streamSettings, ...$transportSettings];
        }

        $outbound['streamSettings'] = $streamSettings;

        return $outbound;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildShadowsocksOutbound(array $parsed): array
    {
        return [
            'protocol' => 'shadowsocks',
            'settings' => [
                'servers' => [[
                    'address' => $parsed['server'],
                    'port' => $parsed['port'],
                    'method' => $parsed['method'],
                    'password' => $parsed['password'],
                    'level' => 8,
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildHysteria2Outbound(array $parsed): array
    {
        return [
            'protocol' => 'hysteria2',
            'settings' => [
                'servers' => [[
                    'address' => $parsed['server'],
                    'port' => $parsed['port'],
                    'password' => $parsed['password'],
                    'alpn' => $parsed['alpn'],
                    'sni' => $parsed['sni'],
                    'fingerprint' => $parsed['fp'],
                    'obfs' => $parsed['obfs'],
                    'obfs-password' => $parsed['obfs_password'],
                    'insecure' => $parsed['insecure'],
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildHysteriaOutbound(array $parsed): array
    {
        return [
            'protocol' => 'hysteria',
            'settings' => [
                'servers' => [[
                    'address' => $parsed['server'],
                    'port' => $parsed['port'],
                    'auth_str' => $parsed['auth'],
                    'peer' => $parsed['peer'],
                    'insecure' => $parsed['insecure'],
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildWireGuardOutbound(array $parsed): array
    {
        $peer = array_filter([
            'publicKey' => $parsed['public_key'],
            'preSharedKey' => $parsed['preshared_key'] !== '' ? $parsed['preshared_key'] : null,
            'endpoint' => sprintf('%s:%d', $parsed['server'], (int) $parsed['port']),
            'keepAlive' => $parsed['keepalive'] > 0 ? $parsed['keepalive'] : null,
        ], fn (mixed $value) => $value !== null);

        return [
            'protocol' => 'wireguard',
            'settings' => array_filter([
                'secretKey' => $parsed['private_key'],
                'address' => $this->splitCsv((string) ($parsed['address'] ?? '')),
                'mtu' => $parsed['mtu'] > 0 ? $parsed['mtu'] : null,
                'peers' => [$peer],
                'reserved' => ($reserved = $this->parseReserved((string) ($parsed['reserved'] ?? ''))) !== [] ? $reserved : null,
                'amnezia' => ($parsed['amnezia'] ?? []) !== [] ? $parsed['amnezia'] : null,
            ], fn (mixed $value) => $value !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildTransportSettings(array $parsed): array
    {
        return match ($parsed['transport']) {
            'ws' => [
                'wsSettings' => array_filter([
                    'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
                    'headers' => $parsed['host'] !== '' ? ['Host' => $parsed['host']] : null,
                ], fn (mixed $value) => $value !== null),
            ],
            'grpc' => [
                'grpcSettings' => array_filter([
                    'serviceName' => $parsed['service_name'] !== '' ? $parsed['service_name'] : 'grpc',
                ], fn (mixed $value) => $value !== null),
            ],
            'http', 'h2' => [
                'httpSettings' => array_filter([
                    'host' => $parsed['host'] !== '' ? [$parsed['host']] : null,
                    'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
                ], fn (mixed $value) => $value !== null),
            ],
            'xhttp' => [
                'xhttpSettings' => array_filter([
                    'host' => $parsed['host'],
                    'mode' => $parsed['mode'] !== '' ? $parsed['mode'] : null,
                    'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
                    'extra' => $parsed['extra'] !== '' ? $parsed['extra'] : null,
                    'xPaddingBytes' => $parsed['x_padding_bytes'] !== '' ? $parsed['x_padding_bytes'] : null,
                ], fn (mixed $value) => $value !== null),
            ],
            default => [],
        };
    }

    private function normalizeVlessNetwork(string $transport): string
    {
        return match ($transport) {
            'h2' => 'http',
            default => $transport !== '' ? $transport : 'tcp',
        };
    }

    /**
     * @return array<int, string>
     */
    private function splitCsv(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function parseReserved(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter(fn (string $item) => $item !== '' && is_numeric($item))
            ->map(fn (string $item) => (int) $item)
            ->values()
            ->all();
    }
}
