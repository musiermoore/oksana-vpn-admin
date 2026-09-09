<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\XrayJsonSetting;
use App\Models\XrayRouting;

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
    public function routing(string $subscriptionType = XrayRouting::SUBSCRIPTION_CONNECT): array
    {
        $settings = $this->activeSettings()?->routing;

        return [
            'domainStrategy' => is_array($settings) && isset($settings['domainStrategy'])
                ? (string) $settings['domainStrategy']
                : (string) config('connect_json.routing.domain_strategy', 'AsIs'),
            'rules' => $this->routingRules($subscriptionType),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routingRules(string $subscriptionType): array
    {
        $rules = XrayRouting::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (XrayRouting $routing): bool => $routing->appliesTo($subscriptionType))
            ->map(fn (XrayRouting $routing): array => $routing->toXrayRule(
                $this->directTag(),
                $this->proxyTag(),
                $this->blockTag(),
            ))
            ->values()
            ->all();

        return $rules !== []
            ? $rules
            : config('connect_json.routing.rules', []);
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
