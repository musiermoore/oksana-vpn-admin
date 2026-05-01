<?php

namespace App\Services\Crud;

use App\DTOs\Transaction\TransactionData;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Telegram\Bot\Laravel\Facades\Telegram;

class TransactionCrudService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
    ) {}

    public function create(TransactionData $data): Transaction
    {
        return $this->transactions->create($data->toArray());
    }

    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        return $this->transactions->update($transaction, $data->toArray());
    }

    public function delete(Transaction $transaction): void
    {
        $this->transactions->delete($transaction);
    }

    public function approve(Transaction $transaction): void
    {
        $this->transactions->update($transaction, ['is_approved' => true]);

        Telegram::sendMessage([
            'chat_id' => $transaction->user->telegram_id,
            'text' => "Баланс пополнен на $transaction->amount",
        ]);
    }

    public function decline(Transaction $transaction): void
    {
        $amount = $transaction->amount;
        $telegramId = $transaction->user->telegram_id;

        $this->transactions->delete($transaction);

        Telegram::sendMessage([
            'chat_id' => $telegramId,
            'text' => "Пополнение баланса на $amount отклонено",
        ]);
    }
}
