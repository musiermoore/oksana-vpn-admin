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
            'password' => $this->password,
            'auth' => $this->auth,
            'link' => $this->link,
            'server' => $this->server ? (new ServerResource($this->server))->toArray($request) : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'is_active' => $this->user->is_active,
            ] : null,
            'links' => [
                'edit' => route('xray-configs.edit', ['protocol' => 'vless', 'config' => $this->resource->getKey()]),
                'destroy' => route('xray-configs.destroy', ['protocol' => 'vless', 'config' => $this->resource->getKey()]),
                'enable' => route('xray-configs.enable', ['protocol' => 'vless', 'config' => $this->resource->getKey()]),
                'disable' => route('xray-configs.disable', ['protocol' => 'vless', 'config' => $this->resource->getKey()]),
            ],
        ];
    }
}
