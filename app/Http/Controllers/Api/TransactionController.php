<?php

namespace App\Http\Controllers\Api;

use App\Models\Transaction;
use App\Models\User;
use App\Services\UserApiService;
use Exception;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;

class TransactionController
{
    public function store(Request $request)
    {
        $user = UserApiService::instance($request->telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'message' => "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler или @musiermoore"
            ], 404);
        }

        try {
            $transaction = $user->transactions()->create([
                'amount' => $request->amount,
                'is_approved' => false
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'message' => "Что-то пошло не так. "
                    . "Сообщи свой никнем @soussangler или @musiermoore"
            ], 500);
        }

        $adminUserIds = User::whereIsAdmin(true)->pluck('telegram_id');

        foreach ($adminUserIds as $chatId) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "$user->full_name пополнил баланс на $transaction->amount ($request->bank)."
            ]);
        }

        return response()->json([
            'message' => "Запрос на пополнение $transaction->amount ($request->bank) отправлен."
        ]);
    }
}
