<?php

namespace App\Services\Api;

use App\DTOs\Transaction\ApiDepositTransactionData;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Repositories\TransactionRepository;
use Telegram\Bot\Laravel\Facades\Telegram;

class ApiTransactionService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
    ) {}

    public function createDepositRequest(User $user, ApiDepositTransactionData $data): Transaction
    {
        $transaction = $this->transactions->createForUser($user, [
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => $data->amount,
            'is_approved' => false,
            'description' => $data->bank,
        ]);

        $this->notifyAdmins($user, $transaction, $data->bank);

        return $transaction;
    }

    private function notifyAdmins(User $user, Transaction $transaction, string $bank): void
    {
        $adminUserIds = User::query()->whereIsAdmin(true)->pluck('telegram_id');

        foreach ($adminUserIds as $chatId) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "$user->full_name пополнил баланс на $transaction->amount ($bank).",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'Принять', 'callback_data' => "approve_deposit|$transaction->id"],
                            ['text' => 'Отклонить', 'callback_data' => "deny_deposit|$transaction->id"],
                        ],
                    ],
                ]),
            ]);
        }
    }
}
