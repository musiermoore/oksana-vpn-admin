<?php

namespace App\Services;

use App\Models\Server;
use App\Services\XuiServices\V3_2_8\XuiConfigService as XuiV3_2_8ConfigService;
use RuntimeException;

class XuiConfigServiceFactory
{
    public static function make(string $version, Server $server): XuiConfigService
    {
        if (! $server->is_active) {
            throw new RuntimeException("Server [{$server->id}] is inactive.");
        }

        return match ($version) {
            Server::PANEL_API_V3_2_8 => new XuiV3_2_8ConfigService($server),
            default => new XuiConfigService($server),
        };
    }
}
