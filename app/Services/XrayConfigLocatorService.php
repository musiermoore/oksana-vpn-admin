<?php

namespace App\Services;

use App\Models\ActiveConnection;
use App\Models\Server;
use App\Models\ShadowsocksConfig;
use App\Models\VlessConfig;
use Illuminate\Database\Eloquent\Model;

class XrayConfigLocatorService
{
    /**
     * @return array{type: string, config: VlessConfig|ShadowsocksConfig, protocol: string}|null
     */
    public function findByServerAndEmail(Server $server, string $email): ?array
    {
        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $vlessConfig = VlessConfig::query()
            ->where('server_id', $server->id)
            ->where('name', $email)
            ->first();

        if ($vlessConfig) {
            return [
                'type' => ActiveConnection::CONFIG_TYPE_VLESS,
                'config' => $vlessConfig,
                'protocol' => (string) ($vlessConfig->protocol ?: 'vless'),
            ];
        }

        $shadowsocksConfig = ShadowsocksConfig::query()
            ->where('server_id', $server->id)
            ->where('name', $email)
            ->first();

        if ($shadowsocksConfig) {
            return [
                'type' => ActiveConnection::CONFIG_TYPE_SHADOWSOCKS,
                'config' => $shadowsocksConfig,
                'protocol' => 'shadowsocks',
            ];
        }

        return null;
    }

    public function findModel(string $type, int $configId): VlessConfig|ShadowsocksConfig|null
    {
        return match ($type) {
            ActiveConnection::CONFIG_TYPE_VLESS => VlessConfig::query()->find($configId),
            ActiveConnection::CONFIG_TYPE_SHADOWSOCKS => ShadowsocksConfig::query()->find($configId),
            default => null,
        };
    }

    public function getCacheTag(Model $config, string $type): string
    {
        return $type.':'.$config->getKey();
    }
}
