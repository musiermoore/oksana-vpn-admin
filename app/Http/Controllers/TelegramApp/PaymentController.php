<?php

declare(strict_types=1);

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreApiTransactionRequest;
use App\Http\Requests\TelegramApp\ActivateSubscriptionCodeRequest;
use App\Http\Requests\TelegramApp\PreviewPromoCodeRequest;
use App\Http\Resources\TelegramApp\PromoCodeQuoteResource;
use App\Models\User;
use App\Services\Api\ApiTransactionService;
use App\Services\SubscriptionCodeService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly ApiTransactionService $transactions,
        private readonly SubscriptionCodeService $subscriptionCodes,
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

    public function previewPromoCode(PreviewPromoCodeRequest $request): JsonResponse
    {
        $data = $request->toData();

        try {
            $quote = $this->transactions->quotePromoCode($request->user(), $data->month, $data->promoCode);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json((new PromoCodeQuoteResource($quote))->resolve($request));
    }

    public function activateSubscriptionCode(ActivateSubscriptionCodeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->toDto();

        try {
            $code = $this->subscriptionCodes->activateForUser($user, $data->code);
            $user->refresh();
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Не удалось активировать код.',
            ], 500);
        }

        return response()->json([
            'status' => 'activated',
            'message' => 'Код активирован. Подписка уже применена к вашему аккаунту.',
            'code' => $code->code,
        ]);
    }
}
