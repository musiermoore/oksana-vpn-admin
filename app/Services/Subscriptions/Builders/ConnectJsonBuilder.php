<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Builders;

use App\DTOs\Subscription\NormalizedNode;
use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Services\Subscriptions\ConnectJsonProfileSettingsProvider;
use App\Services\Subscriptions\SubscriptionUriParser;

class ConnectJsonBuilder
{
    public function __construct(
        private readonly SubscriptionUriParser $parser,
        private readonly ConnectJsonProfileSettingsProvider $settingsProvider,
    ) {}

    /**
     * @param  array<int, NormalizedNode>  $nodes
     */
    public function build(array $nodes): SubscriptionBuildResult
    {
        $outbounds = collect($nodes)
            ->map(fn (NormalizedNode $node) => $this->buildOutbound($node))
            ->filter()
            ->values();

        $proxyTags = $outbounds->pluck('tag')->values()->all();
        $payload = [
            'log' => [
                'level' => $this->settingsProvider->logLevel(),
            ],
            'dns' => $this->settingsProvider->dns(),
            'outbounds' => [
                ...$outbounds->all(),
                [
                    'type' => 'urltest',
                    'tag' => $this->settingsProvider->autoTag(),
                    'outbounds' => $proxyTags,
                    'url' => 'https://www.gstatic.com/generate_204',
                    'interval' => '300s',
                    'tolerance' => 50,
                ],
                [
                    'type' => 'selector',
                    'tag' => $this->settingsProvider->selectorTag(),
                    'outbounds' => array_values(array_unique([$this->settingsProvider->autoTag(), ...$proxyTags])),
                    'default' => $this->settingsProvider->autoTag(),
                    'interrupt_exist_connections' => false,
                ],
                [
                    'type' => 'direct',
                    'tag' => $this->settingsProvider->directTag(),
                ],
                [
                    'type' => 'block',
                    'tag' => $this->settingsProvider->blockTag(),
                ],
                [
                    'type' => 'dns',
                    'tag' => $this->settingsProvider->dnsOutboundTag(),
                ],
            ],
            'route' => $this->settingsProvider->route(),
        ];

        return new SubscriptionBuildResult(
            content: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}',
            contentType: 'application/json; charset=UTF-8',
            fileExtension: 'json',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildOutbound(NormalizedNode $node): ?array
    {
        $parsed = $this->parser->parse($node->uri);

        if (! is_array($parsed)) {
            return null;
        }

        $tag = (string) ($node->meta['name'] ?? $node->serverName);

        return match ($parsed['protocol']) {
            'vless' => $this->buildVlessOutbound($parsed, $tag),
            'trojan' => $this->buildTrojanOutbound($parsed, $tag),
            'shadowsocks' => $this->buildShadowsocksOutbound($parsed, $tag),
            'hysteria2' => $this->buildHysteria2Outbound($parsed, $tag),
            'hysteria' => $this->buildHysteriaOutbound($parsed, $tag),
            'wireguard' => $this->buildWireGuardOutbound($parsed, $tag),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildVlessOutbound(array $parsed, string $tag): array
    {
        $outbound = [
            'type' => 'vless',
            'tag' => $tag,
            'server' => $parsed['server'],
            'server_port' => $parsed['port'],
            'uuid' => $parsed['uuid'],
            'packet_encoding' => 'xudp',
        ];

        if ($parsed['flow'] !== '') {
            $outbound['flow'] = $parsed['flow'];
        }

        if ($parsed['security'] !== '' && $parsed['security'] !== 'none') {
            $tls = [
                'enabled' => true,
            ];

            if ($parsed['sni'] !== '') {
                $tls['server_name'] = $parsed['sni'];
            }

            if ($parsed['fp'] !== '') {
                $tls['utls'] = [
                    'enabled' => true,
                    'fingerprint' => $parsed['fp'],
                ];
            }

            if (($parsed['alpn'] ?? []) !== []) {
                $tls['alpn'] = $parsed['alpn'];
            }

            if ($parsed['security'] === 'reality') {
                $tls['reality'] = array_filter([
                    'enabled' => true,
                    'public_key' => $parsed['pbk'],
                    'short_id' => $parsed['sid'],
                ], fn (mixed $value) => $value !== '');
            }

            $outbound['tls'] = $tls;
        }

        $transport = $this->buildTransport($parsed);

        if ($transport !== null) {
            $outbound['transport'] = $transport;
        }

        return $outbound;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildTrojanOutbound(array $parsed, string $tag): array
    {
        $outbound = [
            'type' => 'trojan',
            'tag' => $tag,
            'server' => $parsed['server'],
            'server_port' => $parsed['port'],
            'password' => $parsed['password'],
        ];

        if ($parsed['security'] !== '' && $parsed['security'] !== 'none') {
            $tls = [
                'enabled' => true,
            ];

            if ($parsed['sni'] !== '') {
                $tls['server_name'] = $parsed['sni'];
            }

            if (($parsed['alpn'] ?? []) !== []) {
                $tls['alpn'] = $parsed['alpn'];
            }

            $outbound['tls'] = $tls;
        }

        $transport = $this->buildTransport($parsed);

        if ($transport !== null) {
            $outbound['transport'] = $transport;
        }

        return $outbound;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildShadowsocksOutbound(array $parsed, string $tag): array
    {
        return [
            'type' => 'shadowsocks',
            'tag' => $tag,
            'server' => $parsed['server'],
            'server_port' => $parsed['port'],
            'method' => $parsed['method'],
            'password' => $parsed['password'],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildHysteria2Outbound(array $parsed, string $tag): array
    {
        $outbound = [
            'type' => 'hysteria2',
            'tag' => $tag,
            'server' => $parsed['server'],
            'server_port' => $parsed['port'],
            'password' => $parsed['password'],
        ];

        $tls = array_filter([
            'enabled' => true,
            'server_name' => $parsed['sni'] !== '' ? $parsed['sni'] : null,
            'insecure' => $parsed['insecure'] ? true : null,
            'alpn' => $parsed['alpn'] !== [] ? $parsed['alpn'] : null,
        ], fn (mixed $value) => $value !== null);

        if ($tls !== []) {
            if ($parsed['fp'] !== '') {
                $tls['utls'] = [
                    'enabled' => true,
                    'fingerprint' => $parsed['fp'],
                ];
            }

            $outbound['tls'] = $tls;
        }

        if ($parsed['obfs'] !== '') {
            $outbound['obfs'] = [
                'type' => $parsed['obfs'],
                'password' => $parsed['obfs_password'],
            ];
        }

        return $outbound;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildHysteriaOutbound(array $parsed, string $tag): array
    {
        return array_filter([
            'type' => 'hysteria',
            'tag' => $tag,
            'server' => $parsed['server'],
            'server_port' => $parsed['port'],
            'auth_str' => $parsed['auth'] !== '' ? $parsed['auth'] : null,
            'up_mbps' => 100,
            'down_mbps' => 100,
            'tls' => array_filter([
                'enabled' => true,
                'server_name' => $parsed['peer'] !== '' ? $parsed['peer'] : null,
                'insecure' => $parsed['insecure'] ? true : null,
            ], fn (mixed $value) => $value !== null),
        ], fn (mixed $value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildWireGuardOutbound(array $parsed, string $tag): array
    {
        return array_filter([
            'type' => 'wireguard',
            'tag' => $tag,
            'server' => $parsed['server'],
            'server_port' => $parsed['port'],
            'local_address' => $this->splitCsv((string) ($parsed['address'] ?? '')),
            'private_key' => $parsed['private_key'],
            'peer_public_key' => $parsed['public_key'],
            'pre_shared_key' => $parsed['preshared_key'] !== '' ? $parsed['preshared_key'] : null,
            'mtu' => $parsed['mtu'] > 0 ? $parsed['mtu'] : null,
            'persistent_keepalive_interval' => $parsed['keepalive'] > 0 ? $parsed['keepalive'] : null,
            'dns' => ($dns = $this->splitCsv((string) ($parsed['dns'] ?? ''))) !== [] ? $dns : null,
            'reserved' => ($reserved = $this->parseReserved((string) ($parsed['reserved'] ?? ''))) !== [] ? $reserved : null,
        ], fn (mixed $value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>|null
     */
    private function buildTransport(array $parsed): ?array
    {
        return match ($parsed['transport']) {
            'ws' => array_filter([
                'type' => 'ws',
                'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
                'headers' => $parsed['host'] !== '' ? ['Host' => $parsed['host']] : null,
            ], fn (mixed $value) => $value !== null),
            'grpc' => array_filter([
                'type' => 'grpc',
                'service_name' => $parsed['service_name'] !== '' ? $parsed['service_name'] : 'grpc',
            ], fn (mixed $value) => $value !== null),
            'http', 'h2' => array_filter([
                'type' => 'http',
                'host' => $parsed['host'] !== '' ? [$parsed['host']] : null,
                'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
            ], fn (mixed $value) => $value !== null),
            'xhttp' => array_filter([
                'type' => 'httpupgrade',
                'host' => $parsed['host'] !== '' ? $parsed['host'] : null,
                'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
            ], fn (mixed $value) => $value !== null),
            default => null,
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
