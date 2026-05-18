<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreApiTransactionRequest;
use App\Models\Transaction;
use App\Services\Api\ApiTransactionService;
use App\Services\Crud\TransactionCrudService;
use App\Support\BotApiMessages;
use Throwable;

class TransactionController
{
    public function __construct(
        private readonly TransactionCrudService $transactionService,
        private readonly ApiTransactionService $apiTransactionService,
    ) {}

    public function store(StoreApiTransactionRequest $request)
    {
        try {
            $transaction = $this->apiTransactionService->createDepositRequest(
                $request->user(),
                $request->toDto(),
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }

        return response()->json([
            'message' => "Запрос на пополнение $transaction->amount ({$transaction->description}) отправлен.",
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
