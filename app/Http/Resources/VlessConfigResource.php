<?php

namespace App\Http\Resources;

use App\Models\VlessConfig;

class VlessConfigResource
{
    public static function make(VlessConfig $config): array
    {
        return [
            'id' => $config->id,
            'name' => $config->name,
            'is_active' => (bool) $config->is_active,
            'enable' => (bool) $config->enable,
            'link' => $config->link,
            'server' => $config->server ? ServerResource::make($config->server) : null,
            'user' => $config->user ? [
                'id' => $config->user->id,
                'full_name' => $config->user->full_name,
                'is_active' => $config->user->is_active,
            ] : null,
            'links' => [
                'edit' => route('vless-configs.edit', $config),
                'destroy' => route('vless-configs.destroy', $config),
                'enable' => route('vless-configs.enable', $config),
                'disable' => route('vless-configs.disable', $config),
            ],
        ];
    }
}
