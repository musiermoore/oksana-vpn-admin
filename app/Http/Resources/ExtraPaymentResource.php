<?php

namespace App\Http\Resources;

use App\Models\UserExtraPayment;

class ExtraPaymentResource
{
    public static function make(UserExtraPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'user' => $payment->user ? [
                'id' => $payment->user->id,
                'full_name' => $payment->user->full_name,
                'is_active' => $payment->user->is_active,
                'edit_url' => route('users.edit', $payment->user),
            ] : null,
            'current_payment' => $payment->currentPayment ? [
                'id' => $payment->currentPayment->id,
                'full_date' => $payment->currentPayment->full_date,
            ] : null,
            'links' => [
                'destroy' => route('extra-payments.destroy', $payment),
            ],
        ];
    }
}
