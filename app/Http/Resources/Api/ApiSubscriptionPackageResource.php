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
            'payable_now' => (float) $this['payable_now'],
            'balance_before' => (float) $this['balance_before'],
            'balance_applied' => (float) $this['balance_applied'],
            'discount_percent' => (int) $this['discount_percent'],
        ];
    }
}
