<?php

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
            'port' => (int) $this->port,
            'server_id' => $this->server_id !== null ? (int) $this->server_id : null,
            'server_name' => $this->whenLoaded('server', fn () => $this->server?->name),
            'sort_order' => (int) $this->sort_order,
            'inbound_id' => $this->inbound_id !== null ? (int) $this->inbound_id : null,
            'xray_inbound_id' => $this->xray_inbound_id !== null ? (int) $this->xray_inbound_id : null,
            'is_https' => (bool) $this->is_https,
            'is_ready' => (bool) $this->is_ready,
            'description' => $this->description,
            'links' => [
                'edit' => route('proxies.edit', $this->resource),
                'destroy' => route('proxies.destroy', $this->resource),
            ],
        ];
    }
}
