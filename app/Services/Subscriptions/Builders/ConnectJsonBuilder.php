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
        $profiles = collect($nodes)
            ->map(fn (NormalizedNode $node) => $this->buildProfile($node))
            ->filter()
            ->values()
            ->all();

        return new SubscriptionBuildResult(
            content: json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]',
            contentType: 'application/json; charset=UTF-8',
            fileExtension: 'json',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildProfile(NormalizedNode $node): ?array
    {
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
            'wireguard' => $this->buildWireGuardOutbound($parsed),
            default => null,
        };

        if ($proxyOutbound === null) {
            return null;
        }

        $proxyOutbound['tag'] = $this->settingsProvider->proxyTag();

        return [
            'remarks' => (string) ($node->meta['name'] ?? $node->serverName),
            'log' => $this->settingsProvider->log(),
            'dns' => $this->settingsProvider->dns(),
            'routing' => $this->settingsProvider->routing(),
            'inbounds' => $this->settingsProvider->inbounds(),
            'outbounds' => [
                $proxyOutbound,
                $this->settingsProvider->directOutbound(),
                $this->settingsProvider->blockOutbound(),
            ],
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
