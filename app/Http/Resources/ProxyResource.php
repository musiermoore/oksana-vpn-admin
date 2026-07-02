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
            'is_https' => (bool) $this->is_https,
            'is_ready' => (bool) $this->is_ready,
            'description' => $this->description,
            'linked_servers_count' => (int) ($this->linked_servers_count ?? $this->servers_count ?? 0),
            'links' => [
                'edit' => route('proxies.edit', $this->resource),
                'destroy' => route('proxies.destroy', $this->resource),
            ],
        ];
    }
}
