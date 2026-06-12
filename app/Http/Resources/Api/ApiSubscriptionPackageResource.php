<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiSubscriptionPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'month' => (int) $this['month'],
            'price' => (float) $this['price'],
            'discount_percent' => (int) $this['discount_percent'],
        ];
    }
}
