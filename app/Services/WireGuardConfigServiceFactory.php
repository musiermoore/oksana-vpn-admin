<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Server;
use App\Services\Contracts\WireGuardConfigServiceContract;
use RuntimeException;

class WireGuardConfigServiceFactory
{
    public static function make(Config $config): WireGuardConfigServiceContract
    {
        $server = $config->server;

        if (! $server) {
            throw new RuntimeException('Server is required to resolve a WireGuard config service.');
        }

        return match ($server->type) {
            Server::TYPE_WIREGUARD => WireGuardAgentConfigService::instance($config),
            Server::TYPE_WIREGUARD_OLD => WireGuardConfigService::instance($config),
            default => throw new RuntimeException(sprintf(
                'Server type [%s] does not support WireGuard config management.',
                $server->type,
            )),
        };
    }
}
