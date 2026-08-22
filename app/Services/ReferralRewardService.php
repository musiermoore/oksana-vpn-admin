<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReferralRewardStatus;
use App\Jobs\ProcessReferralRewardJob;
use App\Models\Referral;
use App\Models\SubscriptionCode;
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
        $rewardData = $this->resolveRewardData($transaction);

        if ($rewardData === null) {
            return;
        }

        $this->scheduleReward(
            user: $user,
            transaction: $transaction,
            rewardData: $rewardData,
            qualifiedAt: Carbon::parse($transaction->created_at),
        );
    }

    public function scheduleForSubscriptionCodeActivation(User $user, SubscriptionCode $code): void
    {
        $rewardData = $this->resolveRewardDataFromSubscriptionCode($code);

        if ($rewardData === null || ! $code->transaction) {
            return;
        }

        $qualifiedAt = $code->activated_at
            ? Carbon::parse($code->activated_at)
            : now();

        $this->scheduleReward(
            user: $user,
            transaction: $code->transaction,
            rewardData: $rewardData,
            qualifiedAt: $qualifiedAt,
        );
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

        $qualifyingSource = $this->resolveFirstQualifyingRewardSource($user);

        if ($qualifyingSource === null) {
            return $referral;
        }

        if ($qualifyingSource['type'] === 'transaction') {
            $this->scheduleForSubscriptionPurchase($user, $qualifyingSource['transaction']);
        } else {
            $this->scheduleForSubscriptionCodeActivation($user, $qualifyingSource['code']);
        }

        $referral = $referral->fresh();

        if ($referral?->reward_scheduled_at !== null && ! $referral->reward_scheduled_at->isFuture()) {
            $this->processReward($referral);

            return $referral->fresh();
        }

        return $referral;
    }

    /**
     * @return array{processed:int,rewarded:int,scheduled:int,pending_without_purchase:int,skipped_missing_user:int}
     */
    public function backfillPendingRewards(?int $referralId = null): array
    {
        $stats = [
            'processed' => 0,
            'rewarded' => 0,
            'scheduled' => 0,
            'pending_without_purchase' => 0,
            'skipped_missing_user' => 0,
        ];

        Referral::query()
            ->with('referralUser')
            ->where('reward_status', ReferralRewardStatus::Pending->value)
            ->whereNull('qualifying_transaction_id')
            ->whereNull('rewarded_at')
            ->when($referralId !== null, fn ($query) => $query->whereKey($referralId))
            ->orderBy('id')
            ->chunkById(100, function ($referrals) use (&$stats): void {
                foreach ($referrals as $referral) {
                    $stats['processed']++;

                    if (! $referral->referralUser) {
                        $stats['skipped_missing_user']++;

                        continue;
                    }

                    $updatedReferral = $this->backfillFirstPurchaseReward($referral->referralUser);
                    $freshReferral = $updatedReferral?->fresh() ?? $referral->fresh();

                    if (! $freshReferral instanceof Referral) {
                        $stats['skipped_missing_user']++;

                        continue;
                    }

                    if ($freshReferral->reward_status === ReferralRewardStatus::WaitingConfirmation
                        && $this->isRewardDueForBackfill($freshReferral)
                    ) {
                        $this->processReward($freshReferral, true);
                        $freshReferral = $freshReferral->fresh();
                    }

                    if ($freshReferral->reward_status === ReferralRewardStatus::Rewarded) {
                        $stats['rewarded']++;

                        continue;
                    }

                    if ($freshReferral->reward_status === ReferralRewardStatus::WaitingConfirmation) {
                        $stats['scheduled']++;

                        continue;
                    }

                    $stats['pending_without_purchase']++;
                }
            });

        return $stats;
    }

    /**
     * @param array{months:int, bonus_days:int, reward_percent:int} $rewardData
     */
    private function scheduleReward(User $user, Transaction $transaction, array $rewardData, Carbon $qualifiedAt): void
    {
        if ($user->referrer_id === null) {
            return;
        }

        $referral = DB::transaction(function () use ($user, $transaction, $rewardData, $qualifiedAt): ?Referral {
            $referral = $this->referrals->findByReferralUserIdForUpdate($user->id);

            if (! $referral || $referral->qualifying_transaction_id !== null || $referral->rewarded_at !== null) {
                return null;
            }

            return $this->referrals->update($referral, [
                'qualifying_transaction_id' => $transaction->id,
                'invitee_bonus_days' => $rewardData['bonus_days'],
                'referrer_reward_percent' => $rewardData['reward_percent'],
                'reward_status' => ReferralRewardStatus::WaitingConfirmation,
                'reward_scheduled_at' => $qualifiedAt->copy()->addDay()->max(now()),
            ]);
        });

        if (! $referral) {
            return;
        }

        ProcessReferralRewardJob::dispatch($referral->id)
            ->delay($referral->reward_scheduled_at);
    }

    /**
     * @return array{type:'transaction',transaction:Transaction}|array{type:'code',code:SubscriptionCode}|null
     */
    private function resolveFirstQualifyingRewardSource(User $user): ?array
    {
        $candidate = null;

        foreach ($this->referrals->findPotentialQualifyingTransactions($user) as $transaction) {
            $rewardData = $this->resolveRewardData($transaction);

            if ($rewardData === null) {
                continue;
            }

            $candidate = [
                'type' => 'transaction',
                'qualified_at' => Carbon::parse($transaction->created_at),
                'sort_id' => (int) $transaction->id,
                'payload' => $transaction,
            ];

            break;
        }

        foreach ($this->referrals->findPotentialQualifyingSubscriptionCodes($user) as $code) {
            $rewardData = $this->resolveRewardDataFromSubscriptionCode($code);

            if ($rewardData === null || ! $code->transaction || ! $code->activated_at) {
                continue;
            }

            $qualifiedAt = Carbon::parse($code->activated_at);

            if ($candidate === null
                || $qualifiedAt->lt($candidate['qualified_at'])
                || ($qualifiedAt->equalTo($candidate['qualified_at']) && $code->id < $candidate['sort_id'])
            ) {
                $candidate = [
                    'type' => 'code',
                    'qualified_at' => $qualifiedAt,
                    'sort_id' => (int) $code->id,
                    'payload' => $code,
                ];
            }
        }

        if ($candidate === null) {
            return null;
        }

        if ($candidate['type'] === 'transaction') {
            return [
                'type' => 'transaction',
                'transaction' => $candidate['payload'],
            ];
        }

        return [
            'type' => 'code',
            'code' => $candidate['payload'],
        ];
    }

    private function isRewardDueForBackfill(Referral $referral): bool
    {
        if ($referral->reward_status !== ReferralRewardStatus::WaitingConfirmation) {
            return false;
        }

        $referral = $this->referrals->findWithRewardDetails($referral->id) ?? $referral;

        $qualifyingCreatedAt = $referral->qualifyingTransaction?->created_at;

        if (! $qualifyingCreatedAt instanceof Carbon || $qualifyingCreatedAt->addDay()->isFuture()) {
            return false;
        }

        return true;
    }

    public function processReward(Referral $referral, bool $ignoreSchedule = false): void
    {
        DB::transaction(function () use ($referral, $ignoreSchedule) {
            $referral = $this->referrals->findForRewardProcessing($referral->id);

            if (! $referral
                || $referral->reward_status !== ReferralRewardStatus::WaitingConfirmation
                || $referral->rewarded_at !== null
                || $referral->reward_scheduled_at === null
                || (! $ignoreSchedule && $referral->reward_scheduled_at->isFuture())
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

    /**
     * @return array{months:int, bonus_days:int, reward_percent:int}|null
     */
    private function resolveRewardDataFromSubscriptionCode(SubscriptionCode $code): ?array
    {
        $months = (int) ($code->months ?? data_get($code->meta, 'subscription_months', 0));

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
