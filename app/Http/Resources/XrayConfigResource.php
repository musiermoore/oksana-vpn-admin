<?php

namespace App\Http\Resources;

use App\Models\ShadowsocksConfig;
use App\Models\VlessConfig;
use Illuminate\Http\Request;

class XrayConfigResource
{
    public function __construct(
        private readonly VlessConfig|ShadowsocksConfig $resource,
        private readonly string $protocol,
    ) {}

    public function toArray(Request $request): array
    {
        $links = [
            'edit' => route('xray-configs.edit', [
                'protocol' => $this->protocol,
                'config' => $this->resource->getKey(),
            ]),
            'destroy' => route('xray-configs.destroy', [
                'protocol' => $this->protocol,
                'config' => $this->resource->getKey(),
            ]),
        ];

        if ($this->resource instanceof VlessConfig) {
            $links['enable'] = route('xray-configs.enable', [
                'protocol' => $this->protocol,
                'config' => $this->resource->getKey(),
            ]);
            $links['disable'] = route('xray-configs.disable', [
                'protocol' => $this->protocol,
                'config' => $this->resource->getKey(),
            ]);
        }

        return [
            'id' => $this->resource->getKey(),
            'protocol' => $this->protocol,
            'protocol_label' => $this->protocol === 'vless' ? 'VLESS' : 'Shadowsocks',
            'name' => $this->resource->name,
            'is_active' => (bool) $this->resource->is_active,
            'enable' => (bool) $this->resource->enable,
            'supports_toggle' => $this->resource instanceof VlessConfig,
            'link' => $this->resource->link,
            'server' => $this->resource->server ? (new ServerResource($this->resource->server))->toArray($request) : null,
            'user' => $this->resource->user ? [
                'id' => $this->resource->user->id,
                'full_name' => $this->resource->user->full_name,
                'is_active' => $this->resource->user->is_active,
            ] : null,
            'links' => $links,
        ];
    }
}
