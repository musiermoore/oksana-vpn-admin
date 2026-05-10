<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtraPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'is_active' => $this->user->is_active,
                'edit_url' => route('users.edit', $this->user),
            ] : null,
            'current_payment' => $this->currentPayment ? [
                'id' => $this->currentPayment->id,
                'full_date' => $this->currentPayment->full_date,
            ] : null,
            'links' => [
                'destroy' => route('extra-payments.destroy', $this->resource),
            ],
        ];
    }
}
