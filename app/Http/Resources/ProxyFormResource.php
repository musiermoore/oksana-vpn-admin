<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProxyFormResource extends ProxyResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'server_id' => $this->server_id !== null ? (int) $this->server_id : null,
        ];
    }
}
