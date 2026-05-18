<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiRegisteredUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'telegram' => $this->telegram,
            'telegram_id' => $this->telegram_id,
            'name' => $this->name,
        ];
    }
}
