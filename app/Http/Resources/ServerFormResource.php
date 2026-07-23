<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ServerFormResource extends ServerResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'panel_password' => $this->panel_password,
            'inbounds' => $this->whenLoaded('xrayInbounds', fn () => $this->xrayInbounds
                ->map(fn ($inbound) => [
                    'id' => (int) $inbound->id,
                    'external_id' => (int) $inbound->external_id,
                    'is_active' => (bool) $inbound->is_active,
                    'is_public' => (bool) $inbound->is_public,
                    'protocol' => (string) data_get($inbound->params, 'protocol', ''),
                    'remark' => (string) data_get($inbound->params, 'remark', ''),
                ])
                ->values()
                ->all(), []),
        ];
    }
}
