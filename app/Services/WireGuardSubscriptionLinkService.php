<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Server;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WireGuardSubscriptionLinkService
{
    public function fromConfig(Config $config): ?string
    {
        $content = $config->server?->isModernWireGuardType()
            ? WireGuardAgentConfigService::instance($config)->getClientConfig()
            : (is_file($config->path) ? (string) file_get_contents($config->path) : '');

        return $this->fromConfigContent($content, $config->server, $config->name);
    }

    public function fromConfigContent(string $content, ?Server $server = null, ?string $name = null): ?string
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        if (Str::startsWith($content, 'wireguard://')) {
            return $this->normalizeExistingUri($content, $name);
        }

        $sections = $this->parseConfigSections($content);
        $interface = $sections['interface'] ?? [];
        $peer = $sections['peer'] ?? [];

        $privateKey = $this->firstNonEmptyString([
            $interface['privatekey'] ?? null,
            $interface['private_key'] ?? null,
        ]);

        $publicKey = $this->firstNonEmptyString([
            $peer['publickey'] ?? null,
            $peer['public_key'] ?? null,
        ]);

        $address = $this->normalizeCsvValue($this->firstNonEmptyString([
            $interface['address'] ?? null,
            $interface['addresses'] ?? null,
        ]));

        [$host, $port] = $this->parseEndpoint(
            $this->firstNonEmptyString([$peer['endpoint'] ?? null]),
            $server,
        );

        return $this->buildUri(
            privateKey: $privateKey,
            host: $host,
            port: $port,
            address: $address,
            publicKey: $publicKey,
            name: $name,
            mtu: $this->nullableInt($interface['mtu'] ?? null),
            dns: $this->normalizeCsvValue($this->firstNonEmptyValue([$interface['dns'] ?? null])),
            presharedKey: $this->firstNonEmptyString([
                $peer['presharedkey'] ?? null,
                $peer['preshared_key'] ?? null,
            ]),
            keepalive: $this->nullableInt($peer['persistentkeepalive'] ?? null),
            reserved: $this->normalizeCsvValue($this->firstNonEmptyValue([
                $interface['reserved'] ?? null,
                $peer['reserved'] ?? null,
            ])),
        );
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @param  array<string, mixed>  $client
     */
    public function fromXui(Server $server, array $inbound, array $client, ?string $name = null): ?string
    {
        $name ??= $this->firstNonEmptyString([
            $client['email'] ?? null,
            $client['name'] ?? null,
        ]);

        foreach ([
            $client['link'] ?? null,
            $client['uri'] ?? null,
            $client['url'] ?? null,
            $client['config'] ?? null,
            $client['clientConfig'] ?? null,
            $client['client_config'] ?? null,
            $client['subscription'] ?? null,
            $client['subscriptionUrl'] ?? null,
            $client['subscription_url'] ?? null,
            Arr::get($client, 'wireguard.link'),
            Arr::get($client, 'wireguard.url'),
            Arr::get($client, 'wireguard.uri'),
            Arr::get($client, 'wireguard.config'),
            Arr::get($client, 'wireguard.clientConfig'),
            Arr::get($client, 'wireguard.client_config'),
        ] as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $candidate = trim($candidate);

            if (Str::startsWith($candidate, 'wireguard://')) {
                return $this->normalizeExistingUri($candidate, $name);
            }

            $uri = $this->fromConfigContent($candidate, $server, $name);

            if ($uri !== null) {
                return $uri;
            }
        }

        [$host, $port] = $this->parseEndpoint($this->firstNonEmptyString([
            $client['endpoint'] ?? null,
            Arr::get($client, 'peer.endpoint'),
            Arr::get($inbound, 'endpoint'),
            Arr::get($inbound, 'settings.endpoint'),
        ]), $server, isset($inbound['port']) ? (int) $inbound['port'] : null);

        return $this->buildUri(
            privateKey: $this->firstNonEmptyString([
                $client['privateKey'] ?? null,
                $client['private_key'] ?? null,
                $client['secretKey'] ?? null,
                $client['secret_key'] ?? null,
                $client['password'] ?? null,
                Arr::get($client, 'wireguard.privateKey'),
                Arr::get($client, 'wireguard.private_key'),
                Arr::get($client, 'wireguard.password'),
            ]),
            host: $host,
            port: $port,
            address: $this->normalizeCsvValue($this->firstNonEmptyValue([
                $client['address'] ?? null,
                $client['addresses'] ?? null,
                $client['allowedIp'] ?? null,
                $client['allowedIPs'] ?? null,
                $client['allowed_ips'] ?? null,
                $client['addressCIDR'] ?? null,
                $client['address_cidr'] ?? null,
                Arr::get($client, 'wireguard.address'),
                Arr::get($client, 'wireguard.addresses'),
                Arr::get($client, 'wireguard.allowedIp'),
                Arr::get($client, 'wireguard.allowedIPs'),
                Arr::get($client, 'wireguard.allowed_ips'),
            ])),
            publicKey: $this->firstNonEmptyString([
                $inbound['public_key'] ?? null,
                $inbound['publicKey'] ?? null,
                Arr::get($inbound, 'settings.publicKey'),
                Arr::get($inbound, 'settings.public_key'),
                $this->derivePublicKeyFromPrivateKey(Arr::get($inbound, 'settings.secretKey')),
                $this->derivePublicKeyFromPrivateKey(Arr::get($inbound, 'settings.secret_key')),
                Arr::get($inbound, 'stream_settings.publicKey'),
                Arr::get($inbound, 'stream_settings.public_key'),
                Arr::get($client, 'peerPublicKey'),
                Arr::get($client, 'peer_public_key'),
            ]),
            name: $name,
            mtu: $this->nullableInt(
                $client['mtu'] ?? $inbound['mtu'] ?? Arr::get($inbound, 'settings.mtu') ?? null
            ),
            dns: $this->normalizeCsvValue($this->firstNonEmptyValue([
                $client['dns'] ?? null,
                $inbound['dns'] ?? null,
                Arr::get($inbound, 'settings.dns'),
            ])),
            presharedKey: $this->firstNonEmptyString([
                $client['presharedKey'] ?? null,
                $client['preshared_key'] ?? null,
                $client['preSharedKey'] ?? null,
            ]),
            keepalive: $this->nullableInt(
                $client['keepAlive'] ?? $client['keepalive'] ?? $client['persistentKeepalive'] ?? null
            ),
            reserved: $this->normalizeCsvValue($this->firstNonEmptyValue([
                $client['reserved'] ?? null,
                Arr::get($client, 'wireguard.reserved'),
            ])),
        );
    }

    private function withName(string $uri, ?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return $uri;
        }

        return Str::before($uri, '#').'#'.rawurlencode($name);
    }

    private function normalizeExistingUri(string $uri, ?string $name = null): ?string
    {
        $parts = parse_url($uri);

        if (! is_array($parts)) {
            return $this->withName($uri, $name);
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        return $this->buildUri(
            privateKey: $this->decodeUriComponent((string) ($parts['user'] ?? '')),
            host: (string) ($parts['host'] ?? ''),
            port: isset($parts['port']) ? (int) $parts['port'] : null,
            address: $this->decodeUriComponent((string) Arr::get($query, 'address', '')),
            publicKey: $this->decodeUriComponent((string) Arr::get($query, 'publickey', '')),
            name: $name ?? $this->decodeUriComponent((string) ($parts['fragment'] ?? '')),
            mtu: $this->nullableInt(Arr::get($query, 'mtu')),
            dns: $this->decodeUriComponent((string) Arr::get($query, 'dns', '')) ?: null,
            presharedKey: $this->decodeUriComponent((string) Arr::get($query, 'presharedkey', '')) ?: null,
            keepalive: $this->nullableInt(Arr::get($query, 'keepalive')),
            reserved: $this->decodeUriComponent((string) Arr::get($query, 'reserved', '')) ?: null,
        );
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

    private function buildUri(
        ?string $privateKey,
        ?string $host,
        ?int $port,
        ?string $address,
        ?string $publicKey,
        ?string $name = null,
        ?int $mtu = null,
        ?string $dns = null,
        ?string $presharedKey = null,
        ?int $keepalive = null,
        ?string $reserved = null,
    ): ?string {
        if (
            blank($privateKey)
            || blank($host)
            || $port === null
            || $port <= 0
            || blank($address)
            || blank($publicKey)
        ) {
            return null;
        }

        $query = array_filter([
            'address' => $address,
            'mtu' => $mtu,
            'publickey' => $publicKey,
            'presharedkey' => $presharedKey,
            'keepalive' => $keepalive,
            'dns' => $dns,
            'reserved' => $reserved,
        ], fn (mixed $value) => ! in_array($value, [null, ''], true));

        $queryString = collect($query)
            ->map(fn (mixed $value, string $key) => $key.'='.rawurlencode($this->stringifyQueryValue($value)))
            ->implode('&');

        return 'wireguard://'
            .rawurlencode($privateKey)
            .'@'
            .$this->formatHost($host)
            .':'
            .$port
            .($queryString !== '' ? '?'.$queryString : '')
            .($name !== null && trim($name) !== '' ? '#'.rawurlencode(trim($name)) : '');
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function parseEndpoint(?string $endpoint, ?Server $server = null, ?int $defaultPort = null): array
    {
        $endpoint = trim((string) $endpoint);

        if ($endpoint !== '') {
            $parsedHost = parse_url(str_contains($endpoint, '://') ? $endpoint : 'udp://'.$endpoint, PHP_URL_HOST);
            $parsedPort = parse_url(str_contains($endpoint, '://') ? $endpoint : 'udp://'.$endpoint, PHP_URL_PORT);

            if (is_string($parsedHost) && $parsedHost !== '') {
                return [$parsedHost, is_int($parsedPort) ? $parsedPort : $defaultPort];
            }
        }

        return [$server?->getLinkAddressHost(), $defaultPort];
    }

    private function firstNonEmptyString(array $values): ?string
    {
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstNonEmptyValue(array $values): mixed
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                if ($value !== []) {
                    return $value;
                }

                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $normalized = trim((string) $value);

            if ($normalized !== '') {
                return $value;
            }
        }

        return null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' && is_numeric($normalized) ? (int) $normalized : null;
    }

    private function normalizeCsvValue(mixed $value): ?string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item) => is_scalar($item) ? trim((string) $item) : '')
                ->filter()
                ->implode(',');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return collect(preg_split('/\s*,\s*/', $value) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->implode(',');
    }

    private function stringifyQueryValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    private function decodeUriComponent(string $value): string
    {
        $decoded = trim($value);

        while ($decoded !== '' && preg_match('/%[0-9A-Fa-f]{2}/', $decoded) === 1) {
            $next = rawurldecode($decoded);

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        return $decoded;
    }

    private function formatHost(string $host): string
    {
        $host = trim($host);

        if ($host === '') {
            return $host;
        }

        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            return '['.$host.']';
        }

        return $host;
    }

    private function derivePublicKeyFromPrivateKey(mixed $privateKey): ?string
    {
        if (! is_scalar($privateKey)) {
            return null;
        }

        $privateKey = trim((string) $privateKey);

        if ($privateKey === '' || ! function_exists('sodium_crypto_scalarmult_base')) {
            return null;
        }

        $decoded = base64_decode($privateKey, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
            return null;
        }

        try {
            return base64_encode(sodium_crypto_scalarmult_base($decoded));
        } catch (\Throwable) {
            return null;
        }
    }
}
