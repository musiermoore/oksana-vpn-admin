<?php

namespace App\Services;

use App\Models\Config;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class WireGuardService
{
    public function getContacts()
    {
        return Config::with('user')->get()->keyBy('name');
    }

    public function getWireguardHandshakes($interface = 'wg0')
    {
        // Execute the wg command and capture the output
        $output = file_get_contents(storage_path('app/wireguard/wg-show.txt'));

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
                    'telegram' => null
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

    public function getClientPeers(): Collection
    {
        $configPath = storage_path('app/configs/');

        $clientPeers = $this->getWireguardHandshakes();
        $contacts = $this->getContacts();
        $files = $this->listFilesInDirectory(storage_path('app/configs'));

        foreach ($files as $file) {
            try {
                $fileContent = File::get($configPath . $file);
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
                        $query->where('created_at', '>=', now()->subMinutes(10));
                    }
                ]);

                if ($index !== -1) {
                    $clientPeers[$index]['name'] = $clientName;
                    $clientPeers[$index]['telegram'] = $config->user->telegram ?? ($clientName . ' (?)');
                    $clientPeers[$index]['config'] = $config;
                }
            }
        }

        return collect($clientPeers)
            ->sortByDesc('latest_handshake')
            ->map(function ($peer) {
                return [
                    'is_active' => $this->isActive($peer),
                    ...$peer
                ];
            })
            ->values();
    }

    public function sortByActive(): array
    {
        $peers = $this->getClientPeers();

        return [
            'active' => $peers->where('is_active', '=', true),
            'inactive' => $peers->where('is_active', '=', false)
        ];
    }

    public function isActive($peer): bool
    {
        preg_match('/^([1-5] minutes?)|(\d{1,2} seconds?)/', $peer['latest_handshake'], $matches);

        return (bool) $matches;
    }
}
