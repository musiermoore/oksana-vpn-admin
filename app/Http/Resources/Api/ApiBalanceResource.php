<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'balance' => max(0, (float) $this->balance),
            'debt' => max(0, -(float) $this->balance),
        ];
    }
}
