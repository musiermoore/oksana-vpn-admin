<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiTransactionRequest;
use App\Models\User;
use App\Services\Api\ApiTransactionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ApiTransactionService $transactions,
    ) {}

    public function purchaseSubscription(StoreApiTransactionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->transactions->purchaseSubscription($user, $request->toDto());
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Payment initialization failed.',
            ], 500);
        }

        return response()->json($result);
    }
}
