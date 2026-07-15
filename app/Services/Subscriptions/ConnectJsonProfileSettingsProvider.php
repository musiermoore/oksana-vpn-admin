<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

class ConnectJsonProfileSettingsProvider
{
    public function logLevel(): string
    {
        return (string) config('connect_json.log.level', 'warn');
    }

    public function autoTag(): string
    {
        return (string) config('connect_json.outbounds.auto_tag', 'Auto');
    }

    public function selectorTag(): string
    {
        return (string) config('connect_json.outbounds.selector_tag', 'Manual');
    }

    public function directTag(): string
    {
        return (string) config('connect_json.outbounds.direct_tag', 'direct');
    }

    public function blockTag(): string
    {
        return (string) config('connect_json.outbounds.block_tag', 'block');
    }

    public function dnsOutboundTag(): string
    {
        return (string) config('connect_json.outbounds.dns_tag', 'dns-out');
    }

    /**
     * @return array<string, mixed>
     */
    public function dns(): array
    {
        return [
            'strategy' => (string) config('connect_json.dns.strategy', 'ipv4_only'),
            'independent_cache' => (bool) config('connect_json.dns.independent_cache', true),
            'servers' => config('connect_json.dns.servers', []),
            'rules' => config('connect_json.dns.rules', []),
            'final' => (string) config('connect_json.dns.final', 'dns-remote'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function route(): array
    {
        return [
            'auto_detect_interface' => (bool) config('connect_json.route.auto_detect_interface', true),
            'final' => (string) config('connect_json.route.final', $this->selectorTag()),
            'rules' => config('connect_json.route.rules', []),
        ];
    }
}
