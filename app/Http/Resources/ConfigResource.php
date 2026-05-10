<?php

namespace App\Http\Resources;

use App\Models\Config;

class ConfigResource
{
    public static function make(Config $config): array
    {
        return [
            'id' => $config->id,
            'name' => $config->name,
            'description' => $config->description,
            'address' => $config->address,
            'is_active' => (bool) $config->is_active,
            'server' => $config->server ? ServerResource::make($config->server) : null,
            'user' => $config->user ? [
                'id' => $config->user->id,
                'full_name' => $config->user->full_name,
                'is_active' => $config->user->is_active,
            ] : null,
            'formatted_last_traffic' => $config->formatted_last_traffic,
            'links' => [
                'edit' => route('configs.edit', $config),
                'destroy' => route('configs.destroy', $config),
                'enable' => route('configs.enable', $config),
                'disable' => route('configs.disable', $config),
            ],
        ];
    }
}
