<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VlessConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'enable' => (bool) $this->enable,
            'link' => $this->link,
            'server' => $this->server ? (new ServerResource($this->server))->toArray($request) : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'is_active' => $this->user->is_active,
            ] : null,
            'links' => [
                'edit' => route('vless-configs.edit', $this->resource),
                'destroy' => route('vless-configs.destroy', $this->resource),
                'enable' => route('vless-configs.enable', $this->resource),
                'disable' => route('vless-configs.disable', $this->resource),
            ],
        ];
    }
}
