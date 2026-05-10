<?php

namespace App\Http\Resources;

use App\Models\Server;

class ServerResource
{
    public static function make(Server $server, bool $includeCredentials = false): array
    {
        $data = [
            'id' => $server->id,
            'name' => $server->name,
            'code' => $server->code,
            'ip' => $server->ip,
            'is_https' => (bool) $server->is_https,
            'link_host' => $server->link_host,
            'panel_link' => $server->panel_link,
            'panel_username' => $server->panel_username,
            'app_path' => $server->app_path,
            'ssh_public_key' => $server->ssh_public_key,
            'is_vless' => (bool) $server->is_vless,
            'links' => [
                'edit' => route('servers.edit', $server),
                'destroy' => route('servers.destroy', $server),
            ],
        ];

        if ($includeCredentials) {
            $data['panel_password'] = $server->panel_password;
        }

        return $data;
    }
}
