<?php

namespace App\Services\Subscriptions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SubscriptionUriTransformer
{
    public function __construct(
        private readonly SubscriptionUriParser $parser,
    ) {}

    public function replaceAddress(string $uri, string $host, int $port): ?string
    {
        $parsed = $this->parser->parse($uri);

        if (! is_array($parsed)) {
            return null;
        }

        return match ($parsed['protocol']) {
            'vless' => $this->buildVless($parsed, $host, $port),
            'trojan' => $this->buildTrojan($parsed, $host, $port),
            'shadowsocks' => $this->buildShadowsocks($parsed, $host, $port),
            'hysteria2' => $this->buildHysteria2($uri, $parsed, $host, $port),
            'hysteria' => $this->buildHysteria($parsed, $host, $port),
            'wireguard' => $this->replaceWireGuardAddress($uri, $host, $port),
            'amneziawg' => $this->replaceAmneziaWireGuardAddress($uri, $parsed, $host, $port),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildVless(array $parsed, string $host, int $port): string
    {
        $query = array_filter([
            'type' => $parsed['transport'],
            'encryption' => $parsed['encryption'],
            'security' => $parsed['security'],
            'flow' => $parsed['flow'],
            'host' => $parsed['host'],
            'path' => $parsed['path'],
            'serviceName' => $parsed['service_name'],
            'mode' => $parsed['mode'],
            'extra' => $parsed['extra'],
            'x_padding_bytes' => $parsed['x_padding_bytes'],
            'sni' => $parsed['sni'],
            'pbk' => $parsed['pbk'],
            'fp' => $parsed['fp'],
            'sid' => $parsed['sid'],
            'spx' => $parsed['spx'],
            'alpn' => implode(',', Arr::wrap($parsed['alpn'] ?? [])),
        ], fn (mixed $value) => ! in_array($value, [null, ''], true));

        return $this->buildStandardUri(
            'vless',
            (string) $parsed['uuid'],
            $host,
            $port,
            $query,
            (string) ($parsed['fragment'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildTrojan(array $parsed, string $host, int $port): string
    {
        $query = array_filter([
            'security' => $parsed['security'],
            'type' => $parsed['transport'],
            'host' => $parsed['host'],
            'path' => $parsed['path'],
            'serviceName' => $parsed['service_name'],
            'sni' => $parsed['sni'],
            'alpn' => implode(',', Arr::wrap($parsed['alpn'] ?? [])),
        ], fn (mixed $value) => ! in_array($value, [null, ''], true));

        return $this->buildStandardUri(
            'trojan',
            (string) $parsed['password'],
            $host,
            $port,
            $query,
            (string) ($parsed['fragment'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildShadowsocks(array $parsed, string $host, int $port): string
    {
        $credentials = rtrim(strtr(base64_encode($parsed['method'].':'.$parsed['password']), '+/', '-_'), '=');
        $query = http_build_query(array_filter([
            'plugin' => $parsed['plugin'],
        ], fn (mixed $value) => ! in_array($value, [null, ''], true)));

        return 'ss://'.$credentials.'@'.$this->formatHost($host).':'.$port
            .($query !== '' ? '?'.$query : '')
            .$this->buildFragment((string) ($parsed['fragment'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildHysteria2(string $sourceUri, array $parsed, string $host, int $port): string
    {
        $scheme = Str::startsWith($sourceUri, 'hy2://') ? 'hy2' : 'hysteria2';
        $query = array_filter([
            'alpn' => implode(',', Arr::wrap($parsed['alpn'] ?? [])),
            'fm' => $parsed['fm'] ?? null,
            'fp' => $parsed['fp'] ?? null,
            'security' => $parsed['security'] ?? null,
            'sni' => $parsed['sni'],
            'insecure' => $parsed['insecure'] ? '1' : null,
            'obfs' => $parsed['obfs'],
            'obfs-password' => $parsed['obfs_password'],
        ], fn (mixed $value) => ! in_array($value, [null, ''], true));

        return $this->buildStandardUri(
            $scheme,
            (string) $parsed['password'],
            $host,
            $port,
            $query,
            (string) ($parsed['fragment'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildHysteria(array $parsed, string $host, int $port): string
    {
        $query = array_filter([
            'protocol' => $parsed['protocol_name'],
            'auth' => $parsed['auth'],
            'peer' => $parsed['peer'],
            'insecure' => $parsed['insecure'] ? '1' : null,
        ], fn (mixed $value) => ! in_array($value, [null, ''], true));

        return $this->buildStandardUri(
            'hysteria',
            null,
            $host,
            $port,
            $query,
            (string) ($parsed['fragment'] ?? ''),
        );
    }

    private function replaceWireGuardAddress(string $uri, string $host, int $port): string
    {
        [$withoutFragment, $fragment] = array_pad(explode('#', $uri, 2), 2, '');
        [$beforeQuery, $query] = array_pad(explode('?', $withoutFragment, 2), 2, '');
        $user = Str::before(Str::after($beforeQuery, 'wireguard://'), '@');

        return 'wireguard://'.$user.'@'.$this->formatHost($host).':'.$port
            .($query !== '' ? '?'.$query : '')
            .$this->buildFragment(rawurldecode($fragment));
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function replaceAmneziaWireGuardAddress(string $uri, array $parsed, string $host, int $port): string
    {
        $content = $this->buildAmneziaWireGuardConfigContent($parsed, $host, $port);
        $payload = rtrim(strtr(base64_encode($content), '+/', '-_'), '=');
        $scheme = Str::startsWith($uri, 'awg://') ? 'awg' : 'amneziawg';

        return $scheme.'://'.$payload.$this->buildFragment((string) ($parsed['fragment'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildAmneziaWireGuardConfigContent(array $parsed, string $host, int $port): string
    {
        $lines = [
            '[Interface]',
            'PrivateKey = '.$parsed['private_key'],
            'Address = '.$parsed['address'],
        ];

        if (($parsed['dns'] ?? '') !== '') {
            $lines[] = 'DNS = '.$parsed['dns'];
        }

        if ((int) ($parsed['mtu'] ?? 0) > 0) {
            $lines[] = 'MTU = '.(int) $parsed['mtu'];
        }

        foreach (($parsed['amnezia'] ?? []) as $key => $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $lines[] = $this->formatAmneziaOptionName((string) $key).' = '.trim((string) $value);
            }
        }

        $lines[] = '';
        $lines[] = '[Peer]';
        $lines[] = 'PublicKey = '.$parsed['public_key'];

        if (($parsed['preshared_key'] ?? '') !== '') {
            $lines[] = 'PresharedKey = '.$parsed['preshared_key'];
        }

        $lines[] = 'Endpoint = '.$this->formatHost($host).':'.$port;
        $lines[] = 'AllowedIPs = 0.0.0.0/0, ::/0';

        if ((int) ($parsed['keepalive'] ?? 0) > 0) {
            $lines[] = 'PersistentKeepalive = '.(int) $parsed['keepalive'];
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function formatAmneziaOptionName(string $key): string
    {
        return match ($key) {
            'jc' => 'Jc',
            'jmin' => 'Jmin',
            'jmax' => 'Jmax',
            'headerprotectionkey' => 'HeaderProtectionKey',
            'contentpaddingaddition' => 'ContentPaddingAddition',
            'randomtrailers' => 'RandomTrailers',
            'disablecookies' => 'DisableCookies',
            'rekeyaftertime' => 'RekeyAfterTime',
            'rekeytimeout' => 'RekeyTimeout',
            'rejectaftertime' => 'RejectAfterTime',
            'keepalivetimeout' => 'KeepaliveTimeout',
            'maxhandshakeattempts' => 'MaxHandshakeAttempts',
            default => strtoupper($key),
        };
    }

    /**
     * @param  array<string, scalar|array|null>  $query
     */
    private function buildStandardUri(
        string $scheme,
        ?string $user,
        string $host,
        int $port,
        array $query,
        string $fragment,
    ): string {
        $queryString = http_build_query($query);

        return $scheme.'://'
            .($user !== null ? rawurlencode($user).'@' : '')
            .$this->formatHost($host)
            .':'.$port
            .($queryString !== '' ? '?'.$queryString : '')
            .$this->buildFragment($fragment);
    }

    private function formatHost(string $host): string
    {
        $normalized = trim($host);

        if ($normalized === '') {
            return $normalized;
        }

        if (str_contains($normalized, ':') && ! str_starts_with($normalized, '[')) {
            return '['.$normalized.']';
        }

        return $normalized;
    }

    private function buildFragment(string $fragment): string
    {
        return $fragment !== '' ? '#'.rawurlencode($fragment) : '';
    }
}
