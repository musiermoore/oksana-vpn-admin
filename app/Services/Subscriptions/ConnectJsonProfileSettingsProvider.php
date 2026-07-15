<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

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
        return [
            'queryStrategy' => (string) config('connect_json.dns.query_strategy', 'UseIPv4'),
            'servers' => config('connect_json.dns.servers', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function routing(): array
    {
        return [
            'domainStrategy' => (string) config('connect_json.routing.domain_strategy', 'AsIs'),
            'rules' => config('connect_json.routing.rules', []),
        ];
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
