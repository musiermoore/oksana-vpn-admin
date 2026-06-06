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
                'server_id' => (int) $config->server->getKey(),
                'server' => $config->server->name,
                'server_sort' => mb_strtolower((string) $config->server->name),
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
                'server_id' => (int) $config->server->getKey(),
                'server' => $config->server->name,
                'server_sort' => mb_strtolower((string) $config->server->name),
                'config_id' => $config->getKey(),
                'config' => $config,
            ]);

        $items = $vlessConfigs
            ->concat($shadowsocksConfigs)
            ->sortBy([
                fn (array $item) => (string) $item['server_sort'],
                fn (array $item) => (int) $item['server_id'],
                fn (array $item) => $this->getTypeSortOrder($item['type']),
                fn (array $item) => (int) $item['config_id'],
            ])
            ->values();

        $displayNames = $this->buildDisplayNames($items->all());

        $links = $items
            ->flatMap(function (array $item) use ($displayNames) {
                $config = $item['config'];
                $displayName = $displayNames[$this->getServerGroupingKey($item)] ?? $config->server->name;

                if ($config instanceof VlessConfig) {
                    return collect($this->getVlessSubscriptionData($config, $displayName))
                        ->map(fn (string $line) => $this->buildLinkItem(
                            $line,
                            $item['server'],
                            $item['server_id'],
                            $item['config_id']
                        ))
                        ->all();
                }

                if ($config instanceof ShadowsocksConfig) {
                    return [
                        $this->buildLinkItem(
                            $this->renameLink($config->getLink(), $displayName),
                            $item['server'],
                            $item['server_id'],
                            $item['config_id']
                        ),
                    ];
                }

                return [];
            })
            ->filter(fn (array $item) => ! empty($item['line']))
            ->unique('line')
            ->groupBy(fn (array $item) => $this->getServerGroupingKey($item))
            ->sortKeys()
            ->flatMap(function ($group, string $serverKey) use ($displayNames) {
                $serverDisplayName = $displayNames[$serverKey] ?? collect($group)->first()['server'] ?? '';
                $sortedGroup = collect($group)
                    ->sortBy([
                        fn (array $item) => $this->getTypeSortOrder($item['type']),
                        fn (array $item) => (int) $item['config_id'],
                    ])
                    ->values();
                $shouldNumberGroup = $sortedGroup->count() > 1;

                return $sortedGroup
                    ->values()
                    ->map(function (array $item, int $index) use ($serverDisplayName, $shouldNumberGroup) {
                        $item['line'] = $this->renameLink(
                            $item['line'],
                            $shouldNumberGroup ? $serverDisplayName.' - '.($index + 1) : $serverDisplayName
                        );

                        return $item;
                    });
            })
            ->values();

        $links = $links
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
     * @param  array<int, array{server: string, server_id: int, server_sort: string}>  $items
     * @return array<string, string>
     */
    private function buildDisplayNames(array $items): array
    {
        return collect($items)
            ->unique(fn (array $item) => $this->getServerGroupingKey($item))
            ->values()
            ->mapWithKeys(fn (array $item) => [
                $this->getServerGroupingKey($item) => (string) $item['server'],
            ])
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
     * @return array{type: string, server: string, server_id: int, server_sort: string, config_id: int, line: string}
     */
    private function buildLinkItem(string $line, string $server, int $serverId, int $configId): array
    {
        return [
            'type' => $this->detectLinkType($line),
            'server' => $server,
            'server_id' => $serverId,
            'server_sort' => mb_strtolower($server),
            'config_id' => $configId,
            'line' => $line,
        ];
    }

    /**
     * @param  array{server: string, server_id?: int, server_sort?: string}  $item
     */
    private function getServerGroupingKey(array $item): string
    {
        return (string) ($item['server_sort'] ?? mb_strtolower((string) $item['server'])).':'.(int) ($item['server_id'] ?? 0);
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
