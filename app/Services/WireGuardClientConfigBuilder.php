<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Server;
use App\Models\VlessConfig;
use App\Services\Subscriptions\SubscriptionUriParser;
use Illuminate\Support\Str;
use RuntimeException;

class WireGuardClientConfigBuilder
{
    public function __construct(
        private readonly SubscriptionUriParser $parser,
    ) {}

    public function buildFromVlessConfig(VlessConfig $config): string
    {
        if (! $this->isWireGuardConfig($config)) {
            throw new RuntimeException('VLESS config is not a WireGuard config.');
        }

        $parsed = $this->parser->parse($config->getStaticLink());

        if (! is_array($parsed) || ($parsed['protocol'] ?? null) !== 'wireguard') {
            throw new RuntimeException('WireGuard link is invalid.');
        }

        $lines = [
            '[Interface]',
            'PrivateKey = '.$parsed['private_key'],
            'Address = '.$parsed['address'],
        ];

        if ((int) ($parsed['mtu'] ?? 0) > 0) {
            $lines[] = 'MTU = '.(int) $parsed['mtu'];
        }

        if (trim((string) ($parsed['dns'] ?? '')) !== '') {
            $lines[] = 'DNS = '.trim((string) $parsed['dns']);
        }

        $lines[] = '';
        $lines[] = '[Peer]';
        $lines[] = 'PublicKey = '.$parsed['public_key'];

        if (trim((string) ($parsed['preshared_key'] ?? '')) !== '') {
            $lines[] = 'PresharedKey = '.trim((string) $parsed['preshared_key']);
        }

        $lines[] = 'Endpoint = '.trim((string) $parsed['server']).':'.(int) $parsed['port'];
        $lines[] = 'AllowedIPs = 0.0.0.0/0, ::/0';

        if ((int) ($parsed['keepalive'] ?? 0) > 0) {
            $lines[] = 'PersistentKeepalive = '.(int) $parsed['keepalive'];
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    public function buildDownloadFilename(VlessConfig $config): string
    {
        $serverName = (string) ($config->server?->name ?? '');
        $serverCode = (string) ($config->server?->code ?? '');

        $normalized = Str::of($serverName !== '' ? $serverName : $serverCode)
            ->slug()
            ->replace('-', '')
            ->replaceMatches('/\d+/', '')
            ->value();

        if ($normalized === '') {
            $normalized = Str::of($config->name)
                ->slug()
                ->replace('-', '')
                ->replaceMatches('/\d+/', '')
                ->value();
        }

        return $normalized !== '' ? $normalized.'.conf' : 'wireguard.conf';
    }

    public function isWireGuardConfig(VlessConfig $config): bool
    {
        return trim(mb_strtolower((string) $config->protocol)) === 'wireguard';
    }
}
