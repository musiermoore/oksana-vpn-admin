<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\XrayJsonSetting;
use App\Models\XrayRouting;
use App\Models\XrayRoutingDnsSettings;
use App\Models\XrayRoutingGeodata;
use Illuminate\Database\Eloquent\Builder;

class ConnectJsonProfileSettingsProvider
{
    /**
     * @return array<string, mixed>
     */
    public function log(): array
    {
        return [
            'loglevel' => (string) config('connect_json.log.level', 'warning'),
        ];
    }

    public function proxyTag(): string
    {
        return (string) config('connect_json.outbounds.proxy_tag', 'proxy');
    }

    public function directTag(): string
    {
        return (string) config('connect_json.outbounds.direct_tag', 'direct');
    }

    public function blockTag(): string
    {
        return (string) config('connect_json.outbounds.block_tag', 'block');
    }

    /**
     * @return array<string, mixed>
     */
    public function dns(): array
    {
        $defaultSettings = XrayRoutingDnsSettings::query()
            ->active()
            ->where('is_default', true)
            ->latest('id')
            ->first();

        if ($defaultSettings !== null) {
            return $this->dnsFromSettings($defaultSettings);
        }

        $settings = $this->activeSettings()?->dns;

        if (is_array($settings) && $settings !== []) {
            return array_filter([
                'hosts' => $settings['hosts'] ?? null,
                'queryStrategy' => $settings['queryStrategy'] ?? (string) config('connect_json.dns.query_strategy', 'UseIPv4'),
                'servers' => $settings['servers'] ?? [],
            ], fn (mixed $value): bool => $value !== null && $value !== []);
        }

        return [
            'queryStrategy' => (string) config('connect_json.dns.query_strategy', 'UseIPv4'),
            'servers' => config('connect_json.dns.servers', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dnsFromSettings(?XrayRoutingDnsSettings $settings): array
    {
        if ($settings === null) {
            return $this->dns();
        }

        return array_filter([
            'servers' => $settings->servers,
            'queryStrategy' => $settings->query_strategy,
            'enableParallelQuery' => $settings->enable_parallel_query,
        ], fn (mixed $value): bool => $value !== null && $value !== [] && $value !== false);
    }

    /**
     * @return array<string, mixed>
     */
    public function routing(
        string $subscriptionType = XrayRouting::SUBSCRIPTION_CONNECT,
        ?int $xrayInboundId = null,
        ?int $externalSubscriptionConfigId = null,
        ?int $proxyId = null,
    ): array {
        $settings = $this->activeSettings()?->routing;

        return [
            'domainStrategy' => is_array($settings) && isset($settings['domainStrategy'])
                ? (string) $settings['domainStrategy']
                : (string) config('connect_json.routing.domain_strategy', 'AsIs'),
            'rules' => $this->routingRules($subscriptionType, $xrayInboundId, $externalSubscriptionConfigId, $proxyId),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function geodata(): ?array
    {
        $assets = data_get($this->activeSettings()?->geodata, 'assets');

        if (! is_array($assets) || $assets === []) {
            return null;
        }

        $xrayAssets = collect($assets)
            ->map(fn (mixed $asset): ?array => is_array($asset)
                && is_string($asset['url'] ?? null)
                && is_string($asset['file'] ?? null)
                    ? [
                        'url' => $asset['url'],
                        'file' => $asset['file'],
                    ]
                    : null)
            ->filter()
            ->values()
            ->all();

        if ($xrayAssets === []) {
            return null;
        }

        return array_filter([
            'cron' => (string) config('connect_json.geodata.cron', '0 4 * * *'),
            'outbound' => (string) config('connect_json.geodata.outbound', $this->proxyTag()),
            'assets' => $xrayAssets,
        ], fn (mixed $value): bool => $value !== '');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function geodataFromSettings(?XrayRoutingGeodata $geodata): ?array
    {
        if ($geodata === null || ! is_array($geodata->assets) || $geodata->assets === []) {
            return null;
        }

        $assets = collect($geodata->assets)
            ->filter(fn (mixed $asset): bool => is_array($asset)
                && is_string($asset['url'] ?? null)
                && is_string($asset['file'] ?? null))
            ->map(fn (array $asset): array => [
                'url' => $asset['url'],
                'file' => $asset['file'],
            ])
            ->values()
            ->all();

        return $assets === [] ? null : [
            'cron' => (string) config('connect_json.geodata.cron', '0 4 * * *'),
            'outbound' => (string) config('connect_json.geodata.outbound', $this->proxyTag()),
            'assets' => $assets,
        ];
    }

    public function hasTargetedRoutingRules(
        string $subscriptionType,
        ?int $xrayInboundId = null,
        ?int $externalSubscriptionConfigId = null,
        ?int $proxyId = null,
    ): bool {
        return XrayRouting::query()
            ->active()
            ->forSubscriptionType($subscriptionType)
            ->where(function (Builder $query) use ($xrayInboundId, $externalSubscriptionConfigId, $proxyId): void {
                $query->where('is_global', true)
                    ->orWhere(fn (Builder $targetQuery) => $targetQuery->forAnyTarget(
                        $xrayInboundId,
                        $externalSubscriptionConfigId,
                        $proxyId,
                    ));
            })
            ->exists();
    }

    /**
     * @param  array<int, int>  $routingIds
     * @return array<int, array<string, mixed>>
     */
    public function customRoutingRules(
        array $routingIds,
        string $subscriptionType,
        ?int $xrayInboundId = null,
        ?int $externalSubscriptionConfigId = null,
        ?int $proxyId = null,
    ): array {
        return XrayRouting::query()
            ->active()
            ->whereKey($routingIds)
            ->forSubscriptionType($subscriptionType)
            ->where(function (Builder $query) use ($xrayInboundId, $externalSubscriptionConfigId, $proxyId): void {
                $query->where('is_global', true)
                    ->orWhere(fn (Builder $targetQuery) => $targetQuery->forAnyTarget(
                        $xrayInboundId,
                        $externalSubscriptionConfigId,
                        $proxyId,
                    ));
            })
            ->ordered()
            ->get()
            ->map(fn (XrayRouting $routing): array => $routing->toXrayRule(
                $this->directTag(),
                $this->proxyTag(),
                $this->blockTag(),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routingRules(
        string $subscriptionType,
        ?int $xrayInboundId,
        ?int $externalSubscriptionConfigId,
        ?int $proxyId = null,
    ): array {
        $query = XrayRouting::query()
            ->active()
            ->forSubscriptionType($subscriptionType);

        if (! (clone $query)->exists()) {
            return config('connect_json.routing.rules', []);
        }

        return $query
            ->where(function (Builder $query) use ($xrayInboundId, $externalSubscriptionConfigId, $proxyId): void {
                $query->where('is_global', true)
                    ->orWhere(fn (Builder $targetQuery) => $targetQuery->forAnyTarget(
                        $xrayInboundId,
                        $externalSubscriptionConfigId,
                        $proxyId,
                    ));
            })
            ->ordered()
            ->get()
            ->map(fn (XrayRouting $routing): array => $routing->toXrayRule(
                $this->directTag(),
                $this->proxyTag(),
                $this->blockTag(),
            ))
            ->values()
            ->all();
    }

    private function activeSettings(): ?XrayJsonSetting
    {
        return XrayJsonSetting::query()
            ->latestActive()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function directOutbound(): array
    {
        return [
            'protocol' => 'freedom',
            'tag' => $this->directTag(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blockOutbound(): array
    {
        return [
            'protocol' => 'blackhole',
            'tag' => $this->blockTag(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inbounds(): array
    {
        return config('connect_json.inbounds', []);
    }
}
