<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LimitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (int) $this->amount,
            'links' => [
                'destroy' => route('limits.destroy', $this->resource),
            ],
        ];
    }
}
