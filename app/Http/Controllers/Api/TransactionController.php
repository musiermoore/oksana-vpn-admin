<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreApiTransactionRequest;
use App\Http\Requests\Api\UpdateApiTransactionTelegramMessageRequest;
use App\Models\Transaction;
use App\Services\Api\ApiTransactionService;
use App\Services\Crud\TransactionCrudService;
use App\Support\BotApiMessages;
use DomainException;
use Symfony\Component\HttpFoundation\Response;
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
            $result = $this->apiTransactionService->purchaseSubscription(
                $request->attributes->get('apiUser'),
                $request->toDto(),
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }

        return response()->json($result);
    }

    public function updateTelegramMessage(
        UpdateApiTransactionTelegramMessageRequest $request,
        string $telegramId,
        string $transactionId,
    ): Response {
        $data = $request->toDto();

        $updated = $this->apiTransactionService->updateTelegramMessageMetadata(
            $request->attributes->get('apiUser'),
            (int) $transactionId,
            $data->telegramChatId,
            $data->telegramMessageId,
        );

        if (! $updated) {
            return response()->json([
                'message' => 'Transaction not found.',
            ], 404);
        }

        return response()->noContent();
    }

    public function approve(Transaction $transaction)
    {
        $this->transactionService->approve($transaction);

        return response()->json([
            'message' => 'Пополнение одобрено.',
        ]);
    }

    public function decline(Transaction $transaction)
    {
        $this->transactionService->decline($transaction);

        return response()->json([
            'message' => 'Пополнение отклонено.',
        ]);
    }
}
