<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProxyFormResource extends ProxyResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'server_ids' => $this->servers->pluck('id')->map(fn (mixed $id) => (int) $id)->values()->all(),
        ];
    }
}
