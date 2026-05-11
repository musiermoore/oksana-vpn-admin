<?php

namespace App\Http\Controllers\Api;

use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Crud\TransactionCrudService;
use App\Services\UserApiService;
use App\Support\BotApiMessages;
use Exception;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;

class TransactionController
{
    public function __construct(
        private readonly TransactionCrudService $transactionService,
    ) {}

    public function store(Request $request)
    {
        $user = UserApiService::instance((string) $request->route('telegramId'))->getUser();

        if (empty($user)) {
            return response()->json([
                'message' => BotApiMessages::userNotFound(),
            ], 404);
        }

        try {
            $transaction = $user->transactions()->create([
                'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
                'amount' => $request->amount,
                'is_approved' => false,
                'description' => $request->bank,
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }

        $adminUserIds = User::whereIsAdmin(true)->pluck('telegram_id');

        foreach ($adminUserIds as $chatId) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "$user->full_name пополнил баланс на $transaction->amount ($request->bank).",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => 'Принять', 'callback_data' => "approve_deposit|$transaction->id"],
                            ['text' => 'Отклонить', 'callback_data' => "deny_deposit|$transaction->id"]
                        ]
                    ]
                ])
            ]);
        }

        return response()->json([
            'message' => "Запрос на пополнение $transaction->amount ($request->bank) отправлен.",
        ]);
    }

    public function approve(Transaction $transaction)
    {
        $this->transactionService->approve($transaction);

        return response()->json([
            'message' => "Пополнение одобрено."
        ]);
    }

    public function decline(Transaction $transaction)
    {
        $this->transactionService->decline($transaction);

        return response()->json([
            'message' => "Пополнение отклонено."
        ]);
    }
}
