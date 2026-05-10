<?php

namespace App\Http\Resources;

use App\Models\CurrentPayment;

class CurrentPaymentResource
{
    public static function make(CurrentPayment $currentPayment): array
    {
        return [
            'id' => $currentPayment->id,
            'start_date' => $currentPayment->start_date,
            'end_date' => $currentPayment->end_date,
            'amount' => (float) $currentPayment->amount,
            'formatted_start_date' => $currentPayment->formatted_start_date,
            'formatted_end_date' => $currentPayment->formatted_end_date,
            'full_date' => $currentPayment->full_date,
            'links' => [
                'edit' => route('current-payments.edit', $currentPayment),
                'destroy' => route('current-payments.destroy', $currentPayment),
            ],
        ];
    }
}
