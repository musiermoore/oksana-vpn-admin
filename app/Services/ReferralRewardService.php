<?php

namespace App\Services;

use App\Enums\ReferralRewardStatus;
use App\Jobs\ProcessReferralRewardJob;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\ReferralRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferralRewardService
{
    private const INVITEE_BONUS_DAYS = [
        1 => 3,
        3 => 7,
        6 => 14,
        12 => 30,
    ];

    private const REFERRER_REWARD_PERCENT = [
        1 => 5,
        3 => 10,
        6 => 15,
        12 => 25,
    ];

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly ReferralRepository $referrals,
    ) {}

    public function scheduleForSubscriptionPurchase(User $user, Transaction $transaction): void
    {
        if ($user->referrer_id === null) {
            return;
        }

        $rewardData = $this->resolveRewardData($transaction);

        if ($rewardData === null) {
            return;
        }

        $referral = DB::transaction(function () use ($user, $transaction, $rewardData): ?Referral {
            $referral = $this->referrals->findByReferralUserIdForUpdate($user->id);

            if (! $referral || $referral->qualifying_transaction_id !== null || $referral->rewarded_at !== null) {
                return null;
            }

            return $this->referrals->update($referral, [
                'qualifying_transaction_id' => $transaction->id,
                'invitee_bonus_days' => $rewardData['bonus_days'],
                'referrer_reward_percent' => $rewardData['reward_percent'],
                'reward_status' => ReferralRewardStatus::WaitingConfirmation,
                'reward_scheduled_at' => Carbon::parse($transaction->created_at)->addDay()->max(now()),
            ]);
        });

        if (! $referral) {
            return;
        }

        ProcessReferralRewardJob::dispatch($referral->id)
            ->delay($referral->reward_scheduled_at);
    }

    public function backfillFirstPurchaseReward(User $user): ?Referral
    {
        if ($user->referrer_id === null) {
            return null;
        }

        $referral = $this->referrals->findByReferralUserId($user->id);

        if (! $referral || $referral->qualifying_transaction_id !== null || $referral->rewarded_at !== null) {
            return $referral;
        }

        $qualifyingTransaction = $this->referrals
            ->findPotentialQualifyingTransactions($user)
            ->first(fn (Transaction $transaction) => $this->resolveRewardData($transaction) !== null);

        if (! $qualifyingTransaction) {
            return $referral;
        }

        $this->scheduleForSubscriptionPurchase($user, $qualifyingTransaction);

        $referral = $referral->fresh();

        if ($referral?->reward_scheduled_at !== null && ! $referral->reward_scheduled_at->isFuture()) {
            $this->processReward($referral);

            return $referral->fresh();
        }

        return $referral;
    }

    public function processReward(Referral $referral): void
    {
        DB::transaction(function () use ($referral) {
            $referral = $this->referrals->findForRewardProcessing($referral->id);

            if (! $referral
                || $referral->reward_status !== ReferralRewardStatus::WaitingConfirmation
                || $referral->rewarded_at !== null
                || $referral->reward_scheduled_at === null
                || $referral->reward_scheduled_at->isFuture()
            ) {
                return;
            }

            if (! $referral->qualifyingTransaction?->is_approved || ! $referral->referrer || ! $referral->referralUser) {
                return;
            }

            $this->subscriptions->grantBonusDays(
                user: $referral->referralUser,
                days: (int) $referral->invitee_bonus_days,
                transaction: $referral->qualifyingTransaction,
                meta: ['referrer_id' => $referral->referrer_id]
            );

            $referral->referrer->increment(
                'referral_accumulated_discount_percent',
                (int) $referral->referrer_reward_percent
            );

            $this->referrals->update($referral, [
                'reward_status' => ReferralRewardStatus::Rewarded,
                'rewarded_at' => now(),
            ]);
        });
    }

    /**
     * @return array{months:int, bonus_days:int, reward_percent:int}|null
     */
    private function resolveRewardData(Transaction $transaction): ?array
    {
        $months = (int) data_get($transaction->extra_data, 'subscription_months', 0);

        if ($months <= 0 && preg_match('/Покупка подписки на (\d+) мес/u', (string) $transaction->description, $matches)) {
            $months = (int) $matches[1];
        }

        $bonusDays = self::INVITEE_BONUS_DAYS[$months] ?? 0;
        $rewardPercent = self::REFERRER_REWARD_PERCENT[$months] ?? 0;

        if ($bonusDays === 0 || $rewardPercent === 0) {
            return null;
        }

        return [
            'months' => $months,
            'bonus_days' => $bonusDays,
            'reward_percent' => $rewardPercent,
        ];
    }
}
