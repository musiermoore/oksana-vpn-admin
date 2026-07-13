<?php

namespace App\Services\Subscriptions\Builders;

use App\DTOs\Subscription\NormalizedNode;
use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Services\Subscriptions\SubscriptionUriParser;
use App\Services\Subscriptions\YamlWriter;

class ClashBuilder implements SubscriptionBuilder
{
    public function __construct(
        private readonly SubscriptionUriParser $parser,
        private readonly YamlWriter $yamlWriter,
    ) {}

    /**
     * @param  array<int, NormalizedNode>  $nodes
     */
    public function build(array $nodes): SubscriptionBuildResult
    {
        $proxies = collect($nodes)
            ->map(fn (NormalizedNode $node) => $this->buildProxy($node))
            ->filter()
            ->values()
            ->all();

        $proxyNames = collect($proxies)
            ->pluck('name')
            ->values()
            ->all();

        $payload = [
            'mixed-port' => 7890,
            'allow-lan' => false,
            'mode' => 'rule',
            'log-level' => 'warning',
            'proxies' => $proxies,
            'proxy-groups' => [
                [
                    'name' => 'Auto',
                    'type' => 'url-test',
                    'proxies' => $proxyNames,
                    'url' => 'https://www.gstatic.com/generate_204',
                    'interval' => 300,
                    'tolerance' => 50,
                ],
                [
                    'name' => 'Manual',
                    'type' => 'select',
                    'proxies' => array_values(array_unique(['Auto', ...$proxyNames])),
                ],
            ],
            'rules' => [
                'MATCH,Manual',
            ],
        ];

        return new SubscriptionBuildResult(
            content: $this->yamlWriter->dump($payload),
            contentType: 'application/yaml; charset=UTF-8',
            fileExtension: 'yaml',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildProxy(NormalizedNode $node): ?array
    {
        $parsed = $this->parser->parse($node->uri);

        if (! is_array($parsed)) {
            return null;
        }

        $name = (string) ($node->meta['name'] ?? $node->serverName);

        return match ($parsed['protocol']) {
            'vless' => $this->buildVlessProxy($parsed, $name),
            'trojan' => $this->buildTrojanProxy($parsed, $name),
            'shadowsocks' => $this->buildShadowsocksProxy($parsed, $name),
            'hysteria2' => $this->buildHysteria2Proxy($parsed, $name),
            'hysteria' => $this->buildHysteriaProxy($parsed, $name),
            'wireguard' => $this->buildWireGuardProxy($parsed, $name),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildVlessProxy(array $parsed, string $name): array
    {
        $proxy = [
            'name' => $name,
            'type' => 'vless',
            'server' => $parsed['server'],
            'port' => $parsed['port'],
            'uuid' => $parsed['uuid'],
            'network' => $parsed['transport'],
            'udp' => true,
            'tls' => $parsed['security'] !== '' && $parsed['security'] !== 'none',
            'servername' => $parsed['sni'] !== '' ? $parsed['sni'] : null,
            'client-fingerprint' => $parsed['fp'] !== '' ? $parsed['fp'] : null,
            'flow' => $parsed['flow'] !== '' ? $parsed['flow'] : null,
        ];

        if ($parsed['security'] === 'reality') {
            $proxy['reality-opts'] = array_filter([
                'public-key' => $parsed['pbk'],
                'short-id' => $parsed['sid'],
            ], fn (mixed $value) => $value !== '');
            $proxy['skip-cert-verify'] = false;
        }

        if ($parsed['transport'] === 'ws') {
            $proxy['ws-opts'] = array_filter([
                'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
                'headers' => $parsed['host'] !== '' ? ['Host' => $parsed['host']] : null,
            ], fn (mixed $value) => $value !== null);
        }

        if ($parsed['transport'] === 'grpc') {
            $proxy['grpc-opts'] = array_filter([
                'grpc-service-name' => $parsed['service_name'],
            ], fn (mixed $value) => $value !== '');
        }

        if ($parsed['transport'] === 'http' || $parsed['transport'] === 'h2') {
            $proxy['http-opts'] = array_filter([
                'path' => $parsed['path'] !== '' ? [$parsed['path']] : null,
                'host' => $parsed['host'] !== '' ? [$parsed['host']] : null,
            ], fn (mixed $value) => $value !== null);
        }

        if ($parsed['transport'] === 'xhttp') {
            $proxy['xhttp-opts'] = array_filter([
                'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
                'host' => $parsed['host'] !== '' ? $parsed['host'] : null,
                'mode' => $parsed['mode'] !== '' ? $parsed['mode'] : null,
            ], fn (mixed $value) => $value !== null && $value !== '');
        }

        return array_filter($proxy, fn (mixed $value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildTrojanProxy(array $parsed, string $name): array
    {
        $proxy = [
            'name' => $name,
            'type' => 'trojan',
            'server' => $parsed['server'],
            'port' => $parsed['port'],
            'password' => $parsed['password'],
            'network' => $parsed['transport'],
            'udp' => true,
            'sni' => $parsed['sni'] !== '' ? $parsed['sni'] : null,
        ];

        if ($parsed['transport'] === 'ws') {
            $proxy['ws-opts'] = array_filter([
                'path' => $parsed['path'] !== '' ? $parsed['path'] : '/',
                'headers' => $parsed['host'] !== '' ? ['Host' => $parsed['host']] : null,
            ], fn (mixed $value) => $value !== null);
        }

        if ($parsed['transport'] === 'grpc') {
            $proxy['grpc-opts'] = array_filter([
                'grpc-service-name' => $parsed['service_name'],
            ], fn (mixed $value) => $value !== '');
        }

        return array_filter($proxy, fn (mixed $value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildShadowsocksProxy(array $parsed, string $name): array
    {
        $proxy = [
            'name' => $name,
            'type' => 'ss',
            'server' => $parsed['server'],
            'port' => $parsed['port'],
            'cipher' => $parsed['method'],
            'password' => $parsed['password'],
            'udp' => true,
        ];

        if ($parsed['plugin'] !== '') {
            [$plugin, $pluginOpts] = array_pad(explode(';', $parsed['plugin'], 2), 2, '');
            $proxy['plugin'] = $plugin;

            if ($pluginOpts !== '') {
                $proxy['plugin-opts'] = [
                    'mode' => $pluginOpts,
                ];
            }
        }

        return $proxy;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildHysteria2Proxy(array $parsed, string $name): array
    {
        return array_filter([
            'name' => $name,
            'type' => 'hysteria2',
            'server' => $parsed['server'],
            'port' => $parsed['port'],
            'password' => $parsed['password'],
            'sni' => $parsed['sni'] !== '' ? $parsed['sni'] : null,
            'skip-cert-verify' => $parsed['insecure'],
            'obfs' => $parsed['obfs'] !== '' ? $parsed['obfs'] : null,
            'obfs-password' => $parsed['obfs_password'] !== '' ? $parsed['obfs_password'] : null,
        ], fn (mixed $value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildHysteriaProxy(array $parsed, string $name): array
    {
        return array_filter([
            'name' => $name,
            'type' => 'hysteria',
            'server' => $parsed['server'],
            'port' => $parsed['port'],
            'auth-str' => $parsed['auth'] !== '' ? $parsed['auth'] : null,
            'protocol' => $parsed['protocol_name'],
            'sni' => $parsed['peer'] !== '' ? $parsed['peer'] : null,
            'skip-cert-verify' => $parsed['insecure'],
            'udp' => true,
        ], fn (mixed $value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function buildWireGuardProxy(array $parsed, string $name): array
    {
        return array_filter([
            'name' => $name,
            'type' => 'wireguard',
            'server' => $parsed['server'],
            'port' => $parsed['port'],
            'ip' => $this->firstCsvValue((string) ($parsed['address'] ?? '')),
            'private-key' => $parsed['private_key'],
            'public-key' => $parsed['public_key'],
            'preshared-key' => $parsed['preshared_key'] !== '' ? $parsed['preshared_key'] : null,
            'mtu' => $parsed['mtu'] > 0 ? $parsed['mtu'] : null,
            'udp' => true,
            'dns' => ($dns = $this->splitCsv((string) ($parsed['dns'] ?? ''))) !== [] ? $dns : null,
            'reserved' => ($reserved = $this->parseReserved((string) ($parsed['reserved'] ?? ''))) !== [] ? $reserved : null,
        ], fn (mixed $value) => $value !== null);
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

    private function firstCsvValue(string $value): string
    {
        return $this->splitCsv($value)[0] ?? '';
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
