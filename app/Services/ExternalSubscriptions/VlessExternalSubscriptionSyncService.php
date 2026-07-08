<?php

namespace App\Services\ExternalSubscriptions;

use App\Models\User;
use App\Models\VlessExternalSubscription;
use App\Models\VlessExternalSubscriptionConfig;
use App\Services\Subscriptions\SubscriptionUriParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class VlessExternalSubscriptionSyncService
{
    public function __construct(
        private readonly SubscriptionUriParser $parser,
    ) {}

    /**
     * @return array{full: array<int, array<string, mixed>>, filtered: array<int, array<string, mixed>>}
     */
    public function preview(array $attributes): array
    {
        return $this->collectConfigs(
            type: (string) ($attributes['type'] ?? ''),
            sourceUrl: (string) ($attributes['source_url'] ?? ''),
            filterPattern: $attributes['filter_pattern'] ?? null,
        );
    }

    public function sync(VlessExternalSubscription $subscription): VlessExternalSubscription
    {
        $result = $this->collectConfigs(
            type: (string) $subscription->type,
            sourceUrl: (string) $subscription->source_url,
            filterPattern: $subscription->filter_pattern,
        );

        DB::transaction(function () use ($subscription, $result): void {
            $rows = collect($result['filtered'])
                ->values()
                ->map(fn (array $item, int $index) => [
                    'config_key' => (string) $item['config_key'],
                    'name' => (string) $item['name'],
                    'normalized_name' => (string) $item['normalized_name'],
                    'protocol' => $item['protocol'] ? (string) $item['protocol'] : null,
                    'url' => (string) $item['url'],
                    'sort_order' => $index,
                ]);

            $keepKeys = $rows->pluck('config_key')->all();

            $subscription->configs()
                ->whereNotIn('config_key', $keepKeys === [] ? ['__none__'] : $keepKeys)
                ->delete();

            foreach ($rows as $row) {
                $subscription->configs()->updateOrCreate(
                    ['config_key' => $row['config_key']],
                    $row
                );
            }

            $subscription->forceFill([
                'last_synced_at' => now(),
                'last_sync_error' => null,
            ])->save();
        });

        return $subscription->fresh('configs');
    }

    public function failSync(VlessExternalSubscription $subscription, string $message): void
    {
        $subscription->forceFill([
            'last_sync_error' => Str::limit($message, 2000, ''),
        ])->save();
    }

    public function hasVisibleConfigsForUser(User $user): bool
    {
        return VlessExternalSubscription::query()
            ->where('is_active', true)
            ->visibleForUser($user)
            ->whereHas('configs')
            ->exists();
    }

    /**
     * @return array<int, VlessExternalSubscriptionConfig>
     */
    public function getVisibleConfigsForUser(User $user): array
    {
        return VlessExternalSubscription::query()
            ->where('is_active', true)
            ->visibleForUser($user)
            ->with('configs')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (VlessExternalSubscription $subscription) => $subscription->configs
                ->map(fn (VlessExternalSubscriptionConfig $config) => $config->setRelation('subscription', $subscription)))
            ->values()
            ->all();
    }

    /**
     * @return array{full: array<int, array<string, mixed>>, filtered: array<int, array<string, mixed>>}
     */
    private function collectConfigs(string $type, string $sourceUrl, ?string $filterPattern): array
    {
        $lines = match ($type) {
            VlessExternalSubscription::TYPE_SUBSCRIPTION => $this->parseSubscriptionLines($sourceUrl),
            VlessExternalSubscription::TYPE_DIRECT => [trim($sourceUrl)],
            default => throw new RuntimeException('Неизвестный тип внешней подписки.'),
        };

        $full = collect($lines)
            ->map(fn (string $line, int $index) => $this->mapLine($line, $index))
            ->filter()
            ->values();

        $normalizedPattern = $type === VlessExternalSubscription::TYPE_SUBSCRIPTION
            ? mb_strtolower(trim((string) $filterPattern))
            : '';

        $filtered = $normalizedPattern === ''
            ? $full
            : $full->filter(fn (array $item) => str_contains((string) $item['normalized_name'], $normalizedPattern))
                ->values();

        return [
            'full' => $full->all(),
            'filtered' => $filtered->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseSubscriptionLines(string $url): array
    {
        $response = Http::timeout(20)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Не удалось загрузить внешнюю подписку.');
        }

        $body = trim((string) $response->body());

        if ($body === '') {
            return [];
        }

        $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?: '', true);
        $content = $decoded !== false && $this->containsSupportedConfig($decoded)
            ? $decoded
            : $body;

        $jsonConfigs = $this->parseJsonProfiles($content);

        if ($jsonConfigs !== []) {
            return $jsonConfigs;
        }

        return collect(preg_split('/\r\n|\r|\n/', $content) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '' && $this->isSupportedSubscriptionLink($line))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapLine(string $line, int $index): ?array
    {
        $parsed = $this->parser->parse($line);

        if (! is_array($parsed)) {
            return null;
        }

        $name = trim((string) ($parsed['fragment'] ?? ''));
        $server = trim((string) ($parsed['server'] ?? ''));
        $protocol = trim((string) ($parsed['protocol'] ?? ''));

        if ($name === '') {
            $name = $server !== '' ? $server : strtoupper($protocol ?: 'config').' #'.($index + 1);
        }

        return [
            'config_key' => sha1($line),
            'name' => $name,
            'normalized_name' => mb_strtolower($name),
            'protocol' => $protocol !== '' ? $protocol : null,
            'url' => $line,
        ];
    }

    private function isSupportedSubscriptionLink(string $line): bool
    {
        return in_array(
            $this->parser->detectProtocol($line),
            ['vless', 'trojan', 'shadowsocks', 'hysteria', 'hysteria2'],
            true
        );
    }

    private function containsSupportedConfig(string $content): bool
    {
        return collect(preg_split('/\r\n|\r|\n/', $content) ?: [])
            ->contains(fn (string $line) => $this->isSupportedSubscriptionLink(trim($line)));
    }

    /**
     * @return array<int, string>
     */
    private function parseJsonProfiles(string $content): array
    {
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn (mixed $profile) => is_array($profile) ? $this->buildUriFromJsonProfile($profile) : null)
            ->filter(fn (mixed $uri) => is_string($uri) && $uri !== '')
            ->values()
            ->all();
    }

    private function buildUriFromJsonProfile(array $profile): ?string
    {
        $outbound = collect($profile['outbounds'] ?? [])
            ->first(function (mixed $item): bool {
                if (! is_array($item)) {
                    return false;
                }

                $protocol = mb_strtolower((string) ($item['protocol'] ?? ''));
                $tag = mb_strtolower((string) ($item['tag'] ?? ''));

                return in_array($protocol, ['vless', 'trojan', 'shadowsocks', 'hysteria2', 'hysteria'], true)
                    && ($tag === 'proxy' || $tag === '' || $protocol !== 'freedom');
            });

        if (! is_array($outbound)) {
            return null;
        }

        $remarks = trim((string) ($profile['remarks'] ?? ''));

        return match (mb_strtolower((string) ($outbound['protocol'] ?? ''))) {
            'vless' => $this->buildVlessUriFromOutbound($outbound, $remarks),
            'trojan' => $this->buildTrojanUriFromOutbound($outbound, $remarks),
            'shadowsocks' => $this->buildShadowsocksUriFromOutbound($outbound, $remarks),
            'hysteria2' => $this->buildHysteria2UriFromOutbound($outbound, $remarks),
            'hysteria' => $this->buildHysteriaUriFromOutbound($outbound, $remarks),
            default => null,
        };
    }

    private function buildVlessUriFromOutbound(array $outbound, string $remarks): ?string
    {
        $server = Arr::first(Arr::get($outbound, 'settings.vnext', []));
        $user = Arr::first(Arr::get($server, 'users', []));

        if (! is_array($server) || ! is_array($user)) {
            return null;
        }

        $stream = is_array($outbound['streamSettings'] ?? null) ? $outbound['streamSettings'] : [];
        $security = mb_strtolower((string) ($stream['security'] ?? 'none'));
        $transport = mb_strtolower((string) ($stream['network'] ?? 'tcp'));
        $tlsSettings = is_array($stream['tlsSettings'] ?? null) ? $stream['tlsSettings'] : [];
        $realitySettings = is_array($stream['realitySettings'] ?? null) ? $stream['realitySettings'] : [];
        $wsSettings = is_array($stream['wsSettings'] ?? null) ? $stream['wsSettings'] : [];
        $grpcSettings = is_array($stream['grpcSettings'] ?? null) ? $stream['grpcSettings'] : [];
        $httpSettings = is_array($stream['httpSettings'] ?? null) ? $stream['httpSettings'] : [];
        $xhttpSettings = is_array($stream['xhttpSettings'] ?? null) ? $stream['xhttpSettings'] : [];

        $params = array_filter([
            'type' => $transport ?: 'tcp',
            'encryption' => (string) ($user['encryption'] ?? 'none'),
            'security' => $security,
            'sni' => (string) ($tlsSettings['serverName'] ?? $realitySettings['serverName'] ?? ''),
            'fp' => (string) ($tlsSettings['fingerprint'] ?? $realitySettings['fingerprint'] ?? ''),
            'alpn' => $this->implodeList($tlsSettings['alpn'] ?? []),
            'pbk' => (string) ($realitySettings['publicKey'] ?? ''),
            'sid' => (string) ($realitySettings['shortId'] ?? ''),
            'spx' => (string) ($realitySettings['spiderX'] ?? ''),
            'host' => (string) (
                $wsSettings['headers']['Host']
                ?? $wsSettings['host']
                ?? $httpSettings['host'][0]
                ?? $httpSettings['host']
                ?? $xhttpSettings['host']
                ?? ''
            ),
            'path' => (string) ($wsSettings['path'] ?? $httpSettings['path'] ?? $xhttpSettings['path'] ?? ''),
            'serviceName' => (string) ($grpcSettings['serviceName'] ?? ''),
            'mode' => (string) ($xhttpSettings['mode'] ?? ''),
            'extra' => $this->normalizeJsonString($xhttpSettings['extra'] ?? null),
            'xPaddingBytes' => isset($xhttpSettings['xPaddingBytes']) ? (string) $xhttpSettings['xPaddingBytes'] : null,
            'flow' => (string) ($user['flow'] ?? ''),
        ], fn (mixed $value) => $value !== null && $value !== '');

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $fragment = rawurlencode($remarks);

        return sprintf(
            'vless://%s@%s:%d?%s#%s',
            rawurlencode((string) ($user['id'] ?? '')),
            (string) ($server['address'] ?? ''),
            (int) ($server['port'] ?? 0),
            $query,
            $fragment
        );
    }

    private function buildTrojanUriFromOutbound(array $outbound, string $remarks): ?string
    {
        $server = Arr::first(Arr::get($outbound, 'settings.servers', []));

        if (! is_array($server)) {
            return null;
        }

        $stream = is_array($outbound['streamSettings'] ?? null) ? $outbound['streamSettings'] : [];
        $transport = mb_strtolower((string) ($stream['network'] ?? 'tcp'));
        $security = mb_strtolower((string) ($stream['security'] ?? 'tls'));
        $wsSettings = is_array($stream['wsSettings'] ?? null) ? $stream['wsSettings'] : [];
        $grpcSettings = is_array($stream['grpcSettings'] ?? null) ? $stream['grpcSettings'] : [];
        $tlsSettings = is_array($stream['tlsSettings'] ?? null) ? $stream['tlsSettings'] : [];

        $params = array_filter([
            'security' => $security,
            'type' => $transport,
            'sni' => (string) ($tlsSettings['serverName'] ?? ''),
            'host' => (string) ($wsSettings['headers']['Host'] ?? $wsSettings['host'] ?? ''),
            'path' => (string) ($wsSettings['path'] ?? ''),
            'serviceName' => (string) ($grpcSettings['serviceName'] ?? ''),
        ], fn (mixed $value) => $value !== '');

        return sprintf(
            'trojan://%s@%s:%d?%s#%s',
            rawurlencode((string) ($server['password'] ?? '')),
            (string) ($server['address'] ?? ''),
            (int) ($server['port'] ?? 0),
            http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            rawurlencode($remarks)
        );
    }

    private function buildShadowsocksUriFromOutbound(array $outbound, string $remarks): ?string
    {
        $server = Arr::first(Arr::get($outbound, 'settings.servers', []));

        if (! is_array($server)) {
            return null;
        }

        $credentials = base64_encode(sprintf(
            '%s:%s',
            (string) ($server['method'] ?? ''),
            (string) ($server['password'] ?? '')
        ));

        $plugin = trim((string) ($server['plugin'] ?? ''));
        $query = $plugin !== '' ? '?'.http_build_query(['plugin' => $plugin], '', '&', PHP_QUERY_RFC3986) : '';

        return sprintf(
            'ss://%s@%s:%d%s#%s',
            rtrim(strtr($credentials, '+/', '-_'), '='),
            (string) ($server['address'] ?? ''),
            (int) ($server['port'] ?? 0),
            $query,
            rawurlencode($remarks)
        );
    }

    private function buildHysteria2UriFromOutbound(array $outbound, string $remarks): ?string
    {
        $server = Arr::first(Arr::get($outbound, 'settings.servers', []));

        if (! is_array($server)) {
            return null;
        }

        $params = array_filter([
            'sni' => (string) ($server['sni'] ?? ''),
            'alpn' => $this->implodeList($server['alpn'] ?? []),
            'insecure' => ! empty($server['insecure']) ? '1' : '',
            'obfs' => (string) ($server['obfs'] ?? ''),
            'obfs-password' => (string) ($server['obfs-password'] ?? ''),
        ], fn (mixed $value) => $value !== '');

        return sprintf(
            'hysteria2://%s@%s:%d?%s#%s',
            rawurlencode((string) ($server['password'] ?? '')),
            (string) ($server['address'] ?? ''),
            (int) ($server['port'] ?? 0),
            http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            rawurlencode($remarks)
        );
    }

    private function buildHysteriaUriFromOutbound(array $outbound, string $remarks): ?string
    {
        $server = Arr::first(Arr::get($outbound, 'settings.servers', []));

        if (! is_array($server)) {
            return null;
        }

        $params = array_filter([
            'protocol' => (string) ($server['protocol'] ?? 'udp'),
            'auth' => (string) ($server['auth_str'] ?? $server['auth-str'] ?? $server['password'] ?? ''),
            'peer' => (string) ($server['serverName'] ?? $server['sni'] ?? ''),
            'insecure' => ! empty($server['insecure']) ? '1' : '',
        ], fn (mixed $value) => $value !== '');

        return sprintf(
            'hysteria://%s:%d?%s#%s',
            (string) ($server['address'] ?? ''),
            (int) ($server['port'] ?? 0),
            http_build_query($params, '', '&', PHP_QUERY_RFC3986),
            rawurlencode($remarks)
        );
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeJsonString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }

    /**
     * @param  mixed  $value
     */
    private function implodeList(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (! is_array($value)) {
            return '';
        }

        return collect($value)
            ->filter(fn (mixed $item) => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn (mixed $item) => trim((string) $item))
            ->implode(',');
    }
}
