<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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
                    'server' => $server
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

        $clientPeers = [];

        $configPath = storage_path('app/wireguard/clients-' . $server->slug_code);

        $clientPeers = array_merge($clientPeers, $this->getWireguardHandshakes($server));

        if (! File::isDirectory($configPath)) {
            return collect($clientPeers);
        }

        $contacts = $this->getContacts($serverId);
        $files = $this->listFilesInDirectory($configPath);

        foreach ($files as $file) {
            try {
                $fileContent = File::get($configPath . '/' . $file);
            } catch (\Exception $e) {
                continue;
            }

            $clientName = str_replace('.conf', '', $file);
            $lines = explode(PHP_EOL, $fileContent);

            $addressPattern = '/Address = (.*)/';
            $address = null;

            foreach ($lines as $line) {
                $line = trim($line);

                if (preg_match($addressPattern, $line, $matches)) {
                    $address = $matches[1];
                }
            }

            if ($address) {
                $index = $this->findIndexByColumn($clientPeers, 'allowed_ips', str_replace('/24', '/32', $address));

                $config = $contacts[$clientName] ?? null;
                $config?->load([
                    'traffic' => function ($query) {
                        $query
                            ->where('created_at', '>=', $this->startDate)
                            ->where('created_at', '<=', $this->endDate);
                    }
                ])->append(['sent_traffic', 'received_traffic']);

                if ($index !== -1) {
                    $clientPeers[$index]['name'] = $clientName;
                    $clientPeers[$index]['telegram'] = $config->user->telegram ?? ($clientName . ' (?)');
                    $clientPeers[$index]['config'] = $config;
                }
            }
        }

        return collect($clientPeers)
            ->sortByDesc('latest_handshake')
            ->filter(function ($item) use ($serverId) {
                $config = $item['config'] ?? null;
                $trafficTypes = $config->last_traffic ?? [];

                return !$this->filter
                    || (
                        (
                            !$this->userId
                            || $this->userId == $config?->user_id
                        )
                        && (
                            !empty($trafficTypes['sent'])
                            || !empty($trafficTypes['received'])
                        )
                    );
            })
            ->sortByDesc(function ($item) {
                return $item['config']->sent_traffic ?? 0;
            })
            ->map(function ($peer) {
                return [
                    'is_active' => $this->isActive($peer),
                    ...$peer
                ];
            })
            ->values();
    }

    public function sortByActive($serverId): array
    {
        $peers = $this->getClientPeers($serverId);

        return [
            'active' => $peers->where('is_active', '=', true),
            'inactive' => $peers->where('is_active', '=', false)
        ];
    }

    public function isActive($peer): bool
    {
        preg_match('/^(([1-5] minutes?)|(\d{1,2} seconds?))/', $peer['latest_handshake'], $matches);

        return (bool) $matches;
    }
}
