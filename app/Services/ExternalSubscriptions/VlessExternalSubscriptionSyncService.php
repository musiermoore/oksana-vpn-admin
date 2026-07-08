<?php

namespace App\Services\ExternalSubscriptions;

use App\Models\User;
use App\Models\VlessExternalSubscription;
use App\Models\VlessExternalSubscriptionConfig;
use App\Services\Subscriptions\SubscriptionUriParser;
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
}
