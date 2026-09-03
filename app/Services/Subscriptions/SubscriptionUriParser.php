<?php

namespace App\Services\Subscriptions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SubscriptionUriParser
{
    /**
     * @return array<string, mixed>|null
     */
    public function parse(string $uri): ?array
    {
        $scheme = $this->detectProtocol($uri);

        return match ($scheme) {
            'wireguard' => $this->parseWireGuard($uri),
            'amneziawg' => $this->parseAmneziaWireGuard($uri),
            'vless' => $this->parseVless($uri),
            'trojan' => $this->parseTrojan($uri),
            'shadowsocks' => $this->parseShadowsocks($uri),
            'hysteria2' => $this->parseHysteria2($uri),
            'hysteria' => $this->parseHysteria($uri),
            default => null,
        };
    }

    public function detectProtocol(string $uri): string
    {
        return match (true) {
            Str::startsWith($uri, 'vless://') => 'vless',
            Str::startsWith($uri, 'trojan://') => 'trojan',
            Str::startsWith($uri, 'ss://') => 'shadowsocks',
            Str::startsWith($uri, 'wireguard://') => 'wireguard',
            Str::startsWith($uri, ['amneziawg://', 'awg://']) => 'amneziawg',
            Str::startsWith($uri, 'hy2://'),
            Str::startsWith($uri, 'hysteria2://') => 'hysteria2',
            Str::startsWith($uri, 'hysteria://') => 'hysteria',
            default => 'unknown',
        };
    }

    public function detectTransport(string $uri): string
    {
        $parsed = $this->parse($uri);

        if (! is_array($parsed)) {
            return 'tcp';
        }

        return match ($parsed['protocol']) {
            'wireguard', 'amneziawg' => 'udp',
            'hysteria', 'hysteria2' => 'quic',
            'shadowsocks' => (string) ($parsed['plugin'] ? 'plugin' : 'tcp'),
            default => (string) ($parsed['transport'] ?? 'tcp'),
        };
    }

    private function parseWireGuard(string $uri): ?array
    {
        $normalized = Str::after($uri, 'wireguard://');
        [$mainPart, $fragment] = array_pad(explode('#', $normalized, 2), 2, '');
        [$authority, $queryPart] = array_pad(explode('?', $mainPart, 2), 2, '');

        $atPosition = strrpos($authority, '@');

        if ($atPosition === false) {
            return null;
        }

        $privateKey = substr($authority, 0, $atPosition);
        $endpoint = substr($authority, $atPosition + 1);
        [$host, $port] = $this->parseHostAndPort($endpoint);

        if ($host === '') {
            return null;
        }

        $query = $this->parseQueryString($queryPart);

        return [
            'protocol' => 'wireguard',
            'private_key' => rawurldecode($privateKey),
            'server' => $host,
            'port' => $port,
            'address' => rawurldecode((string) Arr::get($query, 'address', '')),
            'public_key' => rawurldecode((string) Arr::get($query, 'publickey', '')),
            'mtu' => (int) Arr::get($query, 'mtu', 0),
            'preshared_key' => rawurldecode((string) Arr::get($query, 'presharedkey', '')),
            'keepalive' => (int) Arr::get($query, 'keepalive', 0),
            'dns' => rawurldecode((string) Arr::get($query, 'dns', '')),
            'reserved' => rawurldecode((string) Arr::get($query, 'reserved', '')),
            'fragment' => rawurldecode($fragment),
        ];
    }

    private function parseAmneziaWireGuard(string $uri): ?array
    {
        $normalized = Str::after($uri, '://');
        [$payload, $fragment] = array_pad(explode('#', $normalized, 2), 2, '');
        $content = $this->decodeBase64Url(trim($payload)) ?? base64_decode(trim($payload), true);

        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $sections = $this->parseConfigSections($content);
        $interface = $sections['interface'] ?? [];
        $peer = $sections['peer'] ?? [];
        [$host, $port] = $this->parseHostAndPort((string) ($peer['endpoint'] ?? ''));

        if ($host === '') {
            return null;
        }

        return [
            'protocol' => 'amneziawg',
            'private_key' => (string) ($interface['privatekey'] ?? ''),
            'server' => $host,
            'port' => $port,
            'address' => (string) ($interface['address'] ?? ''),
            'public_key' => (string) ($peer['publickey'] ?? ''),
            'mtu' => (int) ($interface['mtu'] ?? 0),
            'preshared_key' => (string) ($peer['presharedkey'] ?? ''),
            'keepalive' => (int) ($peer['persistentkeepalive'] ?? 0),
            'dns' => (string) ($interface['dns'] ?? ''),
            'reserved' => (string) ($interface['reserved'] ?? $peer['reserved'] ?? ''),
            'fragment' => rawurldecode($fragment),
            'amnezia' => $this->extractAmneziaOptions($interface),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function parseConfigSections(string $content): array
    {
        $sections = [];
        $currentSection = null;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            if (preg_match('/^\[(.+)\]$/', $line, $matches) === 1) {
                $currentSection = mb_strtolower(trim($matches[1]));
                $sections[$currentSection] ??= [];

                continue;
            }

            if ($currentSection === null || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            if ($key === '') {
                continue;
            }

            $sections[$currentSection][mb_strtolower($key)] = $value;
        }

        return $sections;
    }

    /**
     * @param  array<string, string>  $interface
     * @return array<string, string>
     */
    private function extractAmneziaOptions(array $interface): array
    {
        $options = [];

        foreach ([
            'jc', 'jmin', 'jmax', 's1', 's2', 's3', 's4', 'h1', 'h2', 'h3', 'h4',
            'i1', 'i2', 'i3', 'i4', 'i5', 'headerprotectionkey', 'contentpaddingaddition',
            'randomtrailers', 'disablecookies', 'rekeyaftertime', 'rekeytimeout',
            'rejectaftertime', 'keepalivetimeout', 'maxhandshakeattempts',
        ] as $key) {
            if (array_key_exists($key, $interface) && trim((string) $interface[$key]) !== '') {
                $options[$key] = trim((string) $interface[$key]);
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function parseQueryString(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $pairs = explode('&', $query);
        $result = [];

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = rawurldecode($key);

            if ($key === '') {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseHostAndPort(string $endpoint): array
    {
        $normalized = trim($endpoint);

        if ($normalized === '') {
            return ['', 0];
        }

        if (str_contains($normalized, '://')) {
            $normalized = (string) parse_url($normalized, PHP_URL_HOST)
                .':'
                .(int) (parse_url($normalized, PHP_URL_PORT) ?? 0);
        }

        if (str_starts_with($normalized, '[')) {
            $closingBracketPosition = strpos($normalized, ']');

            if ($closingBracketPosition === false) {
                return ['', 0];
            }

            $host = substr($normalized, 1, $closingBracketPosition - 1);
            $portPart = ltrim(substr($normalized, $closingBracketPosition + 1), ':');

            return [$host, (int) $portPart];
        }

        $lastColonPosition = strrpos($normalized, ':');

        if ($lastColonPosition === false) {
            return [$normalized, 0];
        }

        return [
            substr($normalized, 0, $lastColonPosition),
            (int) substr($normalized, $lastColonPosition + 1),
        ];
    }

    private function parseVless(string $uri): ?array
    {
        $parts = parse_url($uri);

        if (! is_array($parts)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        return [
            'protocol' => 'vless',
            'uuid' => rawurldecode((string) ($parts['user'] ?? '')),
            'server' => (string) ($parts['host'] ?? ''),
            'port' => (int) ($parts['port'] ?? 0),
            'transport' => $this->normalizeTransport((string) Arr::get($query, 'type', 'tcp')),
            'security' => (string) Arr::get($query, 'security', ''),
            'encryption' => (string) Arr::get($query, 'encryption', 'none'),
            'flow' => (string) Arr::get($query, 'flow', ''),
            'host' => (string) Arr::get($query, 'host', ''),
            'path' => rawurldecode((string) Arr::get($query, 'path', '')),
            'service_name' => rawurldecode((string) Arr::get($query, 'serviceName', '')),
            'mode' => (string) Arr::get($query, 'mode', ''),
            'extra' => rawurldecode((string) Arr::get($query, 'extra', '')),
            'x_padding_bytes' => (string) Arr::get($query, 'x_padding_bytes', Arr::get($query, 'xPaddingBytes', '')),
            'sni' => (string) Arr::get($query, 'sni', ''),
            'pbk' => (string) Arr::get($query, 'pbk', ''),
            'fp' => (string) Arr::get($query, 'fp', ''),
            'sid' => (string) Arr::get($query, 'sid', ''),
            'spx' => rawurldecode((string) Arr::get($query, 'spx', '')),
            'alpn' => $this->splitList((string) Arr::get($query, 'alpn', '')),
            'fragment' => rawurldecode((string) ($parts['fragment'] ?? '')),
        ];
    }

    private function parseTrojan(string $uri): ?array
    {
        $parts = parse_url($uri);

        if (! is_array($parts)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        return [
            'protocol' => 'trojan',
            'password' => rawurldecode((string) ($parts['user'] ?? '')),
            'server' => (string) ($parts['host'] ?? ''),
            'port' => (int) ($parts['port'] ?? 0),
            'transport' => $this->normalizeTransport((string) Arr::get($query, 'type', 'tcp')),
            'security' => (string) Arr::get($query, 'security', ''),
            'host' => (string) Arr::get($query, 'host', ''),
            'path' => rawurldecode((string) Arr::get($query, 'path', '')),
            'service_name' => rawurldecode((string) Arr::get($query, 'serviceName', '')),
            'sni' => (string) Arr::get($query, 'sni', ''),
            'alpn' => $this->splitList((string) Arr::get($query, 'alpn', '')),
            'fragment' => rawurldecode((string) ($parts['fragment'] ?? '')),
        ];
    }

    private function parseShadowsocks(string $uri): ?array
    {
        $withoutScheme = Str::after($uri, 'ss://');
        [$mainPart, $fragment] = array_pad(explode('#', $withoutScheme, 2), 2, '');
        [$credentialsAndHost, $queryPart] = array_pad(explode('?', $mainPart, 2), 2, '');

        parse_str($queryPart, $query);

        if (str_contains($credentialsAndHost, '@')) {
            [$encodedCredentials, $serverPart] = explode('@', $credentialsAndHost, 2);
            $decodedCredentials = $this->decodeBase64Url($encodedCredentials);
        } else {
            $serverPart = $credentialsAndHost;
            $decodedServerPart = $this->decodeBase64Url($credentialsAndHost);

            if ($decodedServerPart === null || ! str_contains($decodedServerPart, '@')) {
                return null;
            }

            [$decodedCredentials, $serverPart] = explode('@', $decodedServerPart, 2);
        }

        if ($decodedCredentials === null || ! str_contains($decodedCredentials, ':')) {
            return null;
        }

        [$method, $password] = explode(':', $decodedCredentials, 2);
        [$server, $port] = array_pad(explode(':', $serverPart, 2), 2, '0');

        return [
            'protocol' => 'shadowsocks',
            'method' => $method,
            'password' => $password,
            'server' => $server,
            'port' => (int) $port,
            'plugin' => rawurldecode((string) Arr::get($query, 'plugin', '')),
            'fragment' => rawurldecode($fragment),
        ];
    }

    private function parseHysteria2(string $uri): ?array
    {
        $normalized = Str::startsWith($uri, 'hy2://')
            ? 'hysteria2://'.Str::after($uri, 'hy2://')
            : $uri;

        $parts = parse_url($normalized);

        if (! is_array($parts)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        return [
            'protocol' => 'hysteria2',
            'password' => rawurldecode((string) ($parts['user'] ?? '')),
            'server' => (string) ($parts['host'] ?? ''),
            'port' => (int) ($parts['port'] ?? 0),
            'alpn' => $this->splitList((string) Arr::get($query, 'alpn', '')),
            'fm' => rawurldecode((string) Arr::get($query, 'fm', '')),
            'fp' => (string) Arr::get($query, 'fp', ''),
            'sni' => (string) Arr::get($query, 'sni', ''),
            'insecure' => $this->toBool(Arr::get($query, 'insecure')),
            'obfs' => (string) Arr::get($query, 'obfs', ''),
            'obfs_password' => (string) Arr::get($query, 'obfs-password', ''),
            'security' => (string) Arr::get($query, 'security', ''),
            'fragment' => rawurldecode((string) ($parts['fragment'] ?? '')),
        ];
    }

    private function parseHysteria(string $uri): ?array
    {
        $parts = parse_url($uri);

        if (! is_array($parts)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        return [
            'protocol' => 'hysteria',
            'server' => (string) ($parts['host'] ?? ''),
            'port' => (int) ($parts['port'] ?? 0),
            'auth' => rawurldecode((string) Arr::get($query, 'auth', '')),
            'protocol_name' => (string) Arr::get($query, 'protocol', 'udp'),
            'peer' => (string) Arr::get($query, 'peer', ''),
            'insecure' => $this->toBool(Arr::get($query, 'insecure')),
            'fragment' => rawurldecode((string) ($parts['fragment'] ?? '')),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function splitList(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeTransport(string $transport): string
    {
        $normalized = trim(mb_strtolower($transport));

        return $normalized !== '' ? $normalized : 'tcp';
    }

    private function toBool(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes'], true);
    }

    private function decodeBase64Url(string $value): ?string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return $decoded === false ? null : $decoded;
    }
}
