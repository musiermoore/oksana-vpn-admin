<?php

namespace App\Services\Crud;

use App\DTOs\Transaction\TransactionData;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Repositories\TransactionRepository;
use Telegram\Bot\Laravel\Facades\Telegram;

class TransactionCrudService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
    ) {}

    public function create(TransactionData $data): Transaction
    {
        return $this->transactions->create($this->normalizeAttributes($data));
    }

    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        return $this->transactions->update($transaction, $this->normalizeAttributes($data));
    }

    public function delete(Transaction $transaction): void
    {
        $this->transactions->delete($transaction);
    }

    public function approve(Transaction $transaction): void
    {
        $transaction = $this->transactions->update($transaction, ['is_approved' => true]);

        Telegram::sendMessage([
            'chat_id' => $transaction->user->telegram_id,
            'text' => $transaction->approval_message_text,
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

    private function normalizeAttributes(TransactionData $data): array
    {
        $attributes = $data->toArray();
        $attributes['amount'] = $this->normalizeAmount(
            typeId: (int) $attributes['type_id'],
            amount: (float) $attributes['amount'],
        );

        return $attributes;
    }

    private function normalizeAmount(int $typeId, float $amount): float
    {
        $normalizedAmount = abs($amount);
        $typeSlug = TransactionType::query()
            ->whereKey($typeId)
            ->value('slug');

        if (in_array($typeSlug, [
            TransactionType::SLUG_SUBSCRIPTION,
            TransactionType::SLUG_EXTRA_PAYMENT,
        ], true)) {
            return -$normalizedAmount;
        }

        return $normalizedAmount;
    }
}
