<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProxyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'hosts' => $this->whenLoaded('hosts', fn () => $this->hosts->map(fn ($host): array => [
                'id' => (int) $host->id,
                'host' => (string) $host->host,
                'port' => (int) $host->port,
                'sort' => (int) $host->sort,
                'is_active' => (bool) $host->is_active,
                'is_https' => (bool) $host->is_https,
                'proxy_server_id' => $host->proxy_server_id === null ? null : (int) $host->proxy_server_id,
                'proxy_server_name' => $host->relationLoaded('proxyServer') ? $host->proxyServer?->name : null,
            ])->values()->all(), []),
            'server_id' => $this->server_id !== null ? (int) $this->server_id : null,
            'proxy_group_id' => $this->proxy_group_id === null ? null : (int) $this->proxy_group_id,
            'proxy_group_name' => $this->whenLoaded('proxyGroup', fn () => $this->proxyGroup?->name),
            'server_name' => $this->whenLoaded('server', fn () => $this->server?->name),
            'sort_order' => (int) $this->sort_order,
            'inbound_id' => $this->inbound_id !== null ? (int) $this->inbound_id : null,
            'xray_inbound_id' => $this->xray_inbound_id !== null ? (int) $this->xray_inbound_id : null,
            'is_https' => (bool) $this->is_https,
            'is_ready' => (bool) $this->is_ready,
            'hide_main_node_name' => (bool) $this->hide_main_node_name,
            'description' => $this->description,
            'links' => [
                'edit' => route('proxies.edit', $this->resource),
                'destroy' => route('proxies.destroy', $this->resource),
                'duplicate' => route('proxies.duplicate', $this->resource),
            ],
        ];
    }
}
