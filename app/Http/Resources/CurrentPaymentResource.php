<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'amount' => (float) $this->amount,
            'formatted_start_date' => $this->formatted_start_date,
            'formatted_end_date' => $this->formatted_end_date,
            'full_date' => $this->full_date,
            'links' => [
                'edit' => route('current-payments.edit', $this->resource),
                'destroy' => route('current-payments.destroy', $this->resource),
            ],
        ];
    }
}
