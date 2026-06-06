<?php

namespace App\Services;

use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VlessSubscriptionService
{
    public function __construct(private readonly User $user)
    {
    }

    public function getAllSubscriptions(): string
    {
        $vlessConfigs = $this->user->vlessConfigs()
            ->where('vless_configs.is_active', true)
            ->where('vless_configs.enable', true)
            ->with('server')
            ->get()
            ->map(fn (VlessConfig $config) => [
                'type' => 'vless',
                'server' => $config->server->name,
                'config_id' => $config->getKey(),
                'config' => $config,
            ]);

        $shadowsocksConfigs = $this->user->shadowsocksConfigs()
            ->where('shadowsocks_configs.is_active', true)
            ->where('shadowsocks_configs.enable', true)
            ->with('server')
            ->get()
            ->map(fn (ShadowsocksConfig $config) => [
                'type' => 'shadowsocks',
                'server' => $config->server->name,
                'config_id' => $config->getKey(),
                'config' => $config,
            ]);

        $items = $vlessConfigs
            ->concat($shadowsocksConfigs)
            ->sortBy([
                fn (array $item) => $this->getTypeSortOrder($item['type']),
                fn (array $item) => mb_strtolower((string) $item['server']),
                fn (array $item) => (int) $item['config_id'],
            ])
            ->values();

        $displayNames = $this->buildDisplayNames($items->all());

        $links = $items
            ->flatMap(function (array $item) use ($displayNames) {
                $config = $item['config'];
                $displayName = $displayNames[$this->getDisplayNameKey($item)] ?? $config->server->name;

                if ($config instanceof VlessConfig) {
                    return collect($this->getVlessSubscriptionData($config, $displayName))
                        ->map(fn (string $line) => $this->buildLinkItem($line, $item['server'], $item['config_id']))
                        ->all();
                }

                if ($config instanceof ShadowsocksConfig) {
                    return [
                        $this->buildLinkItem(
                            $this->renameLink($config->getLink(), $displayName),
                            $item['server'],
                            $item['config_id']
                        ),
                    ];
                }

                return [];
            })
            ->filter(fn (array $item) => ! empty($item['line']))
            ->unique('line')
            ->sortBy([
                fn (array $item) => $this->getTypeSortOrder($item['type']),
                fn (array $item) => mb_strtolower((string) $item['server']),
                fn (array $item) => (int) $item['config_id'],
            ])
            ->pluck('line')
            ->implode("\n");

        return base64_encode($links);
    }

    private function getVlessSubscriptionData(VlessConfig $config, string $displayName): array
    {
        if (empty($config->sub_id)) {
            return [$this->renameLink($config->getStaticLink(), $displayName)];
        }

        try {
            $response = Http::timeout(10)
                ->get($config->getSubscriptionLink())
                ->body();

            $decoded = base64_decode($response, true);

            // Some providers return plain text instead of base64
            if ($decoded === false) {
                $decoded = $response;
            }

            return collect(preg_split('/\r\n|\r|\n/', $decoded))
                ->map(fn ($line) => trim($line))
                ->filter(fn ($line) => ! empty($line) && $this->isSupportedSubscriptionLink($line))
                ->map(fn ($line) => $this->renameLink($line, $displayName))
                ->values()
                ->all();

        } catch (\Exception $e) {
            report($e);
            return [];
        }
    }

    /**
     * @param  array<int, array{type: string, server: string, config_id: int, config: VlessConfig|ShadowsocksConfig}>  $items
     * @return array<int, string>
     */
    private function buildDisplayNames(array $items): array
    {
        $totalByServerName = collect($items)
            ->countBy(fn (array $item) => $item['server']);

        $currentIndexes = [];

        return collect($items)
            ->mapWithKeys(function (array $item) use ($totalByServerName, &$currentIndexes) {
                $serverName = $item['server'];
                $currentIndexes[$serverName] = ($currentIndexes[$serverName] ?? 0) + 1;

                $displayName = $totalByServerName[$serverName] > 1
                    ? "{$serverName} - {$currentIndexes[$serverName]}"
                    : $serverName;

                return [$this->getDisplayNameKey($item) => $displayName];
            })
            ->all();
    }

    private function isSupportedSubscriptionLink(string $line): bool
    {
        return in_array($this->detectLinkType($line), ['vless', 'shadowsocks', 'hysteria', 'hysteria2'], true);
    }

    private function getTypeSortOrder(string $type): int
    {
        return match ($type) {
            'vless' => 0,
            'shadowsocks' => 1,
            'hysteria' => 2,
            'hysteria2' => 3,
            default => 99,
        };
    }

    /**
     * @param  array{type: string, server: string, config_id: int, config: VlessConfig|ShadowsocksConfig}  $item
     */
    private function getDisplayNameKey(array $item): string
    {
        return $item['type'].':'.$item['config_id'];
    }

    /**
     * @return array{type: string, server: string, config_id: int, line: string}
     */
    private function buildLinkItem(string $line, string $server, int $configId): array
    {
        return [
            'type' => $this->detectLinkType($line),
            'server' => $server,
            'config_id' => $configId,
            'line' => $line,
        ];
    }

    private function detectLinkType(string $line): string
    {
        return match (true) {
            str_starts_with($line, 'vless://') => 'vless',
            str_starts_with($line, 'ss://') => 'shadowsocks',
            str_starts_with($line, 'hysteria2://'),
            str_starts_with($line, 'hy2://') => 'hysteria2',
            str_starts_with($line, 'hysteria://') => 'hysteria',
            default => 'unknown',
        };
    }

    private function renameLink(string $link, string $displayName): string
    {
        if (! str_contains($link, '#')) {
            return $link.'#'.rawurlencode($displayName);
        }

        return Str::before($link, '#').'#'.rawurlencode($displayName);
    }
}
