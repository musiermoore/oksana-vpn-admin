<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramApp\ClaimReferralRequest;
use App\Http\Resources\TelegramApp\TelegramAppUserResource;
use App\Models\User;
use App\Services\ReferralRewardService;
use App\Services\ReferralService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Throwable;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
        private readonly ReferralRewardService $referralRewards,
    ) {}

    public function claim(ClaimReferralRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            if ($user->referrer_id !== null) {
                throw new DomainException('Реферер уже привязан и не может быть изменён.');
            }

            $referral = $this->referrals->attachReferral($user, $request->referralInput());

            if ($referral === null) {
                throw new DomainException('Не удалось распознать ссылку или привязать реферера.');
            }

            $this->referralRewards->backfillFirstPurchaseReward($user->fresh());

            $freshUser = $user->fresh();
            $freshUser->setAttribute('referral_summary', $this->referrals->getSummary($freshUser));
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Не удалось сохранить реферальную ссылку.',
            ], 500);
        }

        return response()->json([
            'message' => 'Реферальная связь сохранена.',
            'user' => (new TelegramAppUserResource($freshUser))->resolve(),
        ]);
    }
}
