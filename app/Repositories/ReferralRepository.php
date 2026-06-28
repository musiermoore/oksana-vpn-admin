<?php

namespace App\Repositories;

use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ReferralRepository
{
    public function firstOrCreateForReferralUser(int $referralUserId, int $referrerId): Referral
    {
        return Referral::query()->firstOrCreate(
            ['referral_user_id' => $referralUserId],
            ['referrer_id' => $referrerId]
        );
    }

    public function findByReferralUserId(int $referralUserId): ?Referral
    {
        return Referral::query()
            ->where('referral_user_id', $referralUserId)
            ->first();
    }

    public function findByReferralUserIdForUpdate(int $referralUserId): ?Referral
    {
        return Referral::query()
            ->where('referral_user_id', $referralUserId)
            ->lockForUpdate()
            ->first();
    }

    public function findForRewardProcessing(int $referralId): ?Referral
    {
        return Referral::query()
            ->with(['referrer', 'referralUser', 'qualifyingTransaction'])
            ->lockForUpdate()
            ->find($referralId);
    }

    public function findWithRewardDetails(int $referralId): ?Referral
    {
        return Referral::query()
            ->with(['referrer', 'referralUser', 'qualifyingTransaction'])
            ->find($referralId);
    }

    public function update(Referral $referral, array $attributes): Referral
    {
        $referral->update($attributes);

        return $referral->refresh();
    }

    public function countInvitedUsers(User $user): int
    {
        return $user->referrals()->count();
    }

    public function countActiveReferrals(User $user): int
    {
        return $user->activeReferrals()->count();
    }

    public function findPotentialQualifyingTransactions(User $user): Collection
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('is_approved', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
