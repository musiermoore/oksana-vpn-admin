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
        ];
    }
}
