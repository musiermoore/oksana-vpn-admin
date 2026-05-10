<?php

namespace App\Http\Resources;

use App\Models\Config;
use App\Models\Transaction;
use App\Models\User;

class UserResource
{
    public static function make(User $user, bool $includeRelations = false): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'telegram' => $user->telegram,
            'telegram_id' => $user->telegram_id,
            'description' => $user->description,
            'join_at' => $user->join_at,
            'balance' => (float) ($user->balance ?? 0),
            'is_active' => $user->is_active,
            'full_name' => $user->full_name,
            'approved_transactions_sum_amount' => (float) ($user->approved_transactions_sum_amount ?? 0),
            'payment_amount' => (float) ($user->payment_amount ?? 0),
            'links' => [
                'edit' => route('users.edit', $user),
                'destroy' => route('users.destroy', $user),
            ],
        ];

        if ($includeRelations) {
            $data['configs'] = $user->configs
                ->map(fn (Config $config) => ConfigResource::make($config))
                ->values()
                ->all();
            $data['transactions'] = $user->transactions
                ->map(fn (Transaction $transaction) => TransactionResource::make($transaction))
                ->values()
                ->all();
        }

        return $data;
    }
}
