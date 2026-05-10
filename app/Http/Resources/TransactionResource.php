<?php

namespace App\Http\Resources;

use App\Models\Transaction;

class TransactionResource
{
    public static function make(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'amount' => (float) $transaction->amount,
            'is_approved' => (bool) $transaction->is_approved,
            'description' => $transaction->description,
            'formatted_created_at' => $transaction->formatted_created_at,
            'type' => $transaction->type ? TransactionTypeResource::make($transaction->type) : null,
            'user' => $transaction->user ? [
                'id' => $transaction->user->id,
                'full_name' => $transaction->user->full_name,
                'is_active' => $transaction->user->is_active,
                'edit_url' => route('users.edit', $transaction->user),
            ] : null,
            'links' => [
                'edit' => route('transactions.edit', $transaction),
                'destroy' => route('transactions.destroy', $transaction),
                'approve' => route('transactions.approve', $transaction),
                'decline' => route('transactions.decline', $transaction),
            ],
        ];
    }
}
