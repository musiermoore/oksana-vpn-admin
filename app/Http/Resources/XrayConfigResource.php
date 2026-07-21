<?php

namespace App\Http\Resources;

use App\Models\VlessConfig;
use Illuminate\Http\Request;

class XrayConfigResource
{
    public function __construct(
        private readonly VlessConfig $resource,
        private readonly string $routeProtocol,
    ) {}

    public function toArray(Request $request): array
    {
        $protocol = $this->resolveDisplayProtocol();

        $links = [
            'edit' => route('xray-configs.edit', [
                'protocol' => $this->routeProtocol,
                'config' => $this->resource->getKey(),
            ]),
            'destroy' => route('xray-configs.destroy', [
                'protocol' => $this->routeProtocol,
                'config' => $this->resource->getKey(),
            ]),
        ];

        $links['enable'] = route('xray-configs.enable', [
            'protocol' => $this->routeProtocol,
            'config' => $this->resource->getKey(),
        ]);
        $links['disable'] = route('xray-configs.disable', [
            'protocol' => $this->routeProtocol,
            'config' => $this->resource->getKey(),
        ]);

        return [
            'id' => $this->resource->getKey(),
            'protocol' => $protocol,
            'protocol_label' => $this->formatProtocolLabel($protocol),
            'name' => $this->resource->name,
            'is_active' => (bool) $this->resource->is_active,
            'enable' => (bool) $this->resource->enable,
            'password' => $this->resource->password,
            'auth' => $this->resource->auth,
            'supports_toggle' => true,
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

    private function resolveDisplayProtocol(): string
    {
        return mb_strtolower((string) ($this->resource->protocol ?: 'vless'));
    }

    private function formatProtocolLabel(string $protocol): string
    {
        return match ($protocol) {
            'vless' => 'VLESS',
            'trojan' => 'Trojan',
            'hysteria' => 'Hysteria',
            'hysteria2', 'hy2' => 'Hysteria2',
            default => mb_strtoupper($protocol),
        };
    }
}
