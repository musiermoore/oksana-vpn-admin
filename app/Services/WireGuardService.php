<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class WireGuardService
{
    private Carbon $startDate;

    private Carbon $endDate;

    private bool $filter = false;

    private string|int|null $userId = null;

    public function __construct()
    {
        $this->startDate = now()->subMinutes(10);
        $this->endDate = now();
    }

    public function setStartDate(Carbon $startDate): WireGuardService
    {
        $this->startDate = $startDate->setSeconds(0);

        return $this;
    }

    public function setEndDate(Carbon $endDate): WireGuardService
    {
        $this->endDate = $endDate->setSeconds(59);

        return $this;
    }

    public function setFilter(bool $value): WireGuardService
    {
        $this->filter = $value;

        return $this;
    }

    public function setUserId(int|string|null $userId): WireGuardService
    {
        $this->userId = $userId;

        return $this;
    }

    public function getContacts(int $serverId)
    {
        return Config::with('user')
            ->whereServerId($serverId)
            ->get()
            ->keyBy('name');
    }

    public function getWireguardHandshakes(Server $server)
    {
        if (! $server->isLegacyWireGuardType()) {
            return [];
        }

        $serverCode = $server->slug_code;
        $path = storage_path("app/wireguard/wg-show_$serverCode.txt");

        if (! File::exists($path)) {
            return [];
        }

        // Execute the wg command and capture the output
        $output = file_get_contents($path);

        $peerPattern = '/peer: (.*)/';
        $handshakePattern = '/latest handshake: (.*)/';
        $allowedIpsPattern = '/allowed ips: (.*)/';
        $transferPattern = '/transfer: (.*)/';

        $peers = [];
        $currentPeer = null;

        $lines = explode(PHP_EOL, $output);

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match($peerPattern, $line, $matches)) {
                if ($currentPeer) {
                    $peers[] = $currentPeer;
                }
                $currentPeer = [
                    'peer_id' => $matches[1],
                    'allowed_ips' => null,
                    'latest_handshake' => null,
                    'transfer' => null,
                    'telegram' => null,
                    'server' => $server,
                ];
            } elseif (preg_match($handshakePattern, $line, $matches) && $currentPeer) {
                $currentPeer['latest_handshake'] = $matches[1];
            } elseif (preg_match($allowedIpsPattern, $line, $matches) && $currentPeer) {
                $currentPeer['allowed_ips'] = $matches[1];
            } elseif (preg_match($transferPattern, $line, $matches) && $currentPeer) {
                $currentPeer['transfer'] = $matches[1];
            }
        }

        if ($currentPeer) {
            $peers[] = $currentPeer;
        }

        return $peers;
    }

    public function findIndexByColumn($items, $column, $value)
    {
        foreach ($items as $index => $dictionary) {
            if (isset($dictionary[$column]) && $dictionary[$column] === $value) {
                return $index;
            }
        }

        return -1;  // Return -1 if the id is not found
    }

    public function listFilesInDirectory($directory)
    {
        $files = File::files($directory);
        $fileList = [];

        foreach ($files as $file) {
            if (File::isFile($file)) {
                $fileList[] = $file->getFilename();
            }
        }

        return $fileList;
    }

    public function getClientPeers(int $serverId): Collection
    {
        $server = Server::find($serverId);

        if (! $server || ! $server->isLegacyWireGuardType()) {
            return collect();
        }

        $clientPeers = $this->getWireguardHandshakes($server);
        $configPath = storage_path('app/wireguard/clients-'.$server->slug_code);

        if (! File::isDirectory($configPath)) {
            return collect($clientPeers);
        }

        $clientPeers = $this->attachConfigsToPeers($clientPeers, $configPath, $serverId);

        return $this->formatClientPeers($clientPeers);
    }

    private function attachConfigsToPeers(array $clientPeers, string $configPath, int $serverId): array
    {
        $contacts = $this->getContacts($serverId);

        foreach ($this->listFilesInDirectory($configPath) as $file) {
            $fileContent = $this->readConfigFile($configPath.'/'.$file);

            if ($fileContent === null) {
                continue;
            }

            $address = $this->configAddress($fileContent);

            if ($address === null) {
                continue;
            }

            $clientName = str_replace('.conf', '', $file);
            $index = $this->findIndexByColumn($clientPeers, 'allowed_ips', str_replace('/24', '/32', $address));

            if ($index === -1) {
                continue;
            }

            $config = $contacts[$clientName] ?? null;
            $this->loadRecentTraffic($config);
            $clientPeers[$index]['name'] = $clientName;
            $clientPeers[$index]['telegram'] = $config->user->telegram ?? ($clientName.' (?)');
            $clientPeers[$index]['config'] = $config;
        }

        return $clientPeers;
    }

    private function readConfigFile(string $path): ?string
    {
        try {
            return File::get($path);
        } catch (\Exception $exception) {
            report($exception);

            return null;
        }
    }

    private function configAddress(string $fileContent): ?string
    {
        foreach (explode(PHP_EOL, $fileContent) as $line) {
            if (preg_match('/Address = (.*)/', trim($line), $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function loadRecentTraffic(?Config $config): void
    {
        $config?->load([
            'traffic' => function ($query): void {
                $query
                    ->where('created_at', '>=', $this->startDate)
                    ->where('created_at', '<=', $this->endDate);
            },
        ])->append(['sent_traffic', 'received_traffic']);
    }

    private function formatClientPeers(array $clientPeers): Collection
    {
        return collect($clientPeers)
            ->sortByDesc('latest_handshake')
            ->filter(function ($item) {
                $config = $item['config'] ?? null;
                $trafficTypes = $config->last_traffic ?? [];

                return ! $this->filter
                    || (
                        (
                            ! $this->userId
                            || $this->userId == $config?->user_id
                        )
                        && (
                            ! empty($trafficTypes['sent'])
                            || ! empty($trafficTypes['received'])
                        )
                    );
            })
            ->sortByDesc(fn ($item) => $item['config']->sent_traffic ?? 0)
            ->map(fn ($peer) => ['is_active' => $this->isActive($peer), ...$peer])
            ->values();
    }

    public function sortByActive($serverId): array
    {
        $peers = $this->getClientPeers($serverId);

        return [
            'active' => $peers->where('is_active', '=', true),
            'inactive' => $peers->where('is_active', '=', false),
        ];
    }

    public function isActive($peer): bool
    {
        preg_match('/^(([1-5] minutes?)|(\d{1,2} seconds?))/', $peer['latest_handshake'], $matches);

        return (bool) $matches;
    }
}
