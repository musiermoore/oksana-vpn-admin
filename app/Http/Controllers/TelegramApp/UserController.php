<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiSubscriptionPackageResource;
use App\Http\Resources\TelegramApp\SubscriptionCodeResource;
use App\Http\Resources\TelegramApp\TelegramAppUserResource;
use App\Models\User;
use App\Services\Api\ApiUserService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly ApiUserService $users,
        private readonly ReferralService $referrals,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load([
            'purchasedSubscriptionCodes' => fn ($query) => $query->latest()->limit(10),
        ]);
        $user->setAttribute('referral_summary', $this->referrals->getSummary($user));
        $user->setAttribute(
            'has_money_for_next_subscription_month',
            $this->users->hasMoneyForNextSubscriptionMonth($user)
        );
        $user->setAttribute(
            'subscription_codes_summary',
            SubscriptionCodeResource::collection($user->purchasedSubscriptionCodes)->resolve()
        );

        return response()->json([
            'user' => (new TelegramAppUserResource($user))->resolve(),
        ]);
    }

    public function subscriptionPackages(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => ApiSubscriptionPackageResource::collection(
                $this->users->getSubscriptionPackages($user)
            )->resolve(),
        ]);
    }
}
