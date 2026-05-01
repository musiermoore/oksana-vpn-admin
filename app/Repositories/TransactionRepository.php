<?php

namespace App\Repositories;

use App\Models\Transaction;

class TransactionRepository
{
    public function create(array $attributes): Transaction
    {
        return Transaction::create($attributes);
    }

    public function update(Transaction $transaction, array $attributes): Transaction
    {
        $transaction->update($attributes);

        return $transaction->refresh();
    }

    public function delete(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
