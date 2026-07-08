<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VlessExternalSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'source_url' => $this->source_url,
            'filter_pattern' => $this->filter_pattern,
            'is_active' => (bool) $this->is_active,
            'is_ready' => (bool) $this->is_ready,
            'last_synced_at' => optional($this->last_synced_at)?->toAtomString(),
            'last_sync_error' => $this->last_sync_error,
            'configs_count' => $this->when(isset($this->configs_count), (int) $this->configs_count),
            'configs' => $this->whenLoaded('configs', fn () => $this->configs->map(fn ($config) => [
                'id' => $config->id,
                'name' => $config->name,
                'protocol' => $config->protocol,
                'url' => $config->url,
            ])->values()->all()),
            'links' => [
                'edit' => route('vless-external-subscriptions.edit', $this->resource),
                'sync' => route('vless-external-subscriptions.sync', $this->resource),
            ],
        ];
    }
}
