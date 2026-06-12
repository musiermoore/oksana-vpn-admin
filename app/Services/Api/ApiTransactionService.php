<?php

namespace App\Services\Api;

use App\DTOs\Transaction\ApiDepositTransactionData;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Services\SubscriptionService;
use Telegram\Bot\Laravel\Facades\Telegram;
use Carbon\Carbon;

class ApiTransactionService
{
    public function __construct(
        private readonly TransactionRepository $transactions,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function purchaseSubscription(User $user, ApiDepositTransactionData $data): array
    {
        $quote = $this->subscriptionService->buildPurchaseQuote($user, $data->month);

        if ($quote['deposit_amount'] <= 0) {
            $subscription = $this->subscriptionService->activatePackageForUser(
                user: $user,
                months: $data->month,
                packagePrice: (float) $quote['package_price'],
            );

            $formattedEndDate = Carbon::parse($subscription->end_date)->format('d.m.Y');

            return [
                'status' => 'activated',
                'message' => "Подписка активирована до $formattedEndDate.",
                'end_date' => $subscription->end_date,
                'formatted_end_date' => $formattedEndDate,
            ];
        }

        $transaction = $this->transactions->createForUser($user, [
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => $quote['deposit_amount'],
            'is_approved' => false,
            'description' => $data->bank,
            'extra_data' => [
                'subscription_months' => $data->month,
                'base_month_price' => $quote['base_month_price'],
                'discount_percent' => $quote['discount_percent'],
                'package_full_price' => $quote['package_full_price'],
                'package_price' => $quote['package_price'],
                'balance_before' => $quote['balance_before'],
                'deposit_amount' => $quote['deposit_amount'],
            ],
        ]);

        $this->notifyAdmins($user, $transaction, $data->bank);

        return [
            'status' => 'deposit_required',
            'message' => "Для активации подписки нужно пополнить баланс на {$transaction->amount}.",
            'deposit_amount' => (float) $transaction->amount,
            'transaction_id' => $transaction->id,
        ];
    }

    private function notifyAdmins(User $user, Transaction $transaction, string $bank): void
    {
        $adminUserIds = User::query()->whereIsAdmin(true)->pluck('telegram_id');
        $subscriptionMonths = (int) data_get($transaction->extra_data, 'subscription_months');
        $packagePrice = (float) data_get($transaction->extra_data, 'package_price');
        $discountPercent = (int) data_get($transaction->extra_data, 'discount_percent');

        foreach ($adminUserIds as $chatId) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "$user->full_name запросил пополнение на $transaction->amount ($bank) для подписки на $subscriptionMonths мес. Сумма подписки: $packagePrice, скидка: $discountPercent%.",
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
