<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'is_active' => (bool) $this->is_active,
            'server' => $this->server ? (new ServerResource($this->server))->toArray($request) : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'is_active' => $this->user->is_active,
            ] : null,
            'formatted_last_traffic' => $this->formatted_last_traffic,
            'limits' => $this->whenLoaded('limits', fn () => LimitResource::collection($this->limits)->toArray($request)),
            'links' => [
                'edit' => route('configs.edit', $this->resource),
                'destroy' => route('configs.destroy', $this->resource),
                'enable' => route('configs.enable', $this->resource),
                'disable' => route('configs.disable', $this->resource),
            ],
        ];
    }
}
