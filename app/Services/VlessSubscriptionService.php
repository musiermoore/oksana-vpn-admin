<?php

namespace App\Services;

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
        $configs = $this->user->vlessConfigs()
            ->where('is_active', true)
            ->where('enable', true)
            ->with('server')
            ->get()
            ->values();

        $displayNames = $this->buildDisplayNames($configs->all());

        $links = $configs
            ->flatMap(fn (VlessConfig $config) => $this->getSubscriptionData($config, $displayNames[$config->getKey()] ?? $config->server->name))
            ->filter()
            ->unique()
            ->implode("\n");

        return base64_encode($links);
    }

    private function getSubscriptionData(VlessConfig $config, string $displayName): array
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
                ->filter(fn ($line) => !empty($line) && str_starts_with($line, 'vless://'))
                ->map(fn ($line) => $this->renameLink($line, $displayName))
                ->values()
                ->all();

        } catch (\Exception $e) {
            report($e);
            return [];
        }
    }

    /**
     * @param  array<int, VlessConfig>  $configs
     * @return array<int, string>
     */
    private function buildDisplayNames(array $configs): array
    {
        $totalByServerName = collect($configs)
            ->countBy(fn (VlessConfig $config) => $config->server->name);

        $currentIndexes = [];

        return collect($configs)
            ->mapWithKeys(function (VlessConfig $config) use ($totalByServerName, &$currentIndexes) {
                $serverName = $config->server->name;
                $currentIndexes[$serverName] = ($currentIndexes[$serverName] ?? 0) + 1;

                $displayName = $totalByServerName[$serverName] > 1
                    ? "{$serverName} - {$currentIndexes[$serverName]}"
                    : $serverName;

                return [$config->getKey() => $displayName];
            })
            ->all();
    }

    private function renameLink(string $link, string $displayName): string
    {
        if (! str_contains($link, '#')) {
            return $link.'#'.rawurlencode($displayName);
        }

        return Str::before($link, '#').'#'.rawurlencode($displayName);
    }
}
