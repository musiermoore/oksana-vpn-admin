<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\User;
use App\Repositories\ReferralRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    private const PERMANENT_DISCOUNT_LEVELS = [
        ['from' => 40, 'percent' => 20],
        ['from' => 20, 'percent' => 15],
        ['from' => 10, 'percent' => 10],
        ['from' => 5, 'percent' => 5],
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly ReferralRepository $referrals,
    ) {}

    public function parseReferrerId(?string $startParam): ?int
    {
        $startParam = trim((string) $startParam);

        if (! preg_match('/^ref_(\d+)$/', $startParam, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public function attachReferral(User $user, ?string $startParam): ?Referral
    {
        $referrerId = $this->parseReferralInput($startParam);

        if ($referrerId === null || $referrerId === (int) $user->id || $user->referrer_id !== null) {
            return null;
        }

        $referrer = $this->users->findActiveById($referrerId);

        if (! $referrer) {
            return null;
        }

        return DB::transaction(function () use ($user, $referrer): ?Referral {
            $freshUser = $this->users->lockById($user->id);

            if (! $freshUser || $freshUser->referrer_id !== null || $freshUser->id === $referrer->id) {
                return null;
            }

            $freshUser->update([
                'referrer_id' => $referrer->id,
            ]);

            return $this->referrals->firstOrCreateForReferralUser($freshUser->id, $referrer->id);
        });
    }

    public function parseReferralInput(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $referrerId = $this->parseReferrerId($value);

        if ($referrerId !== null) {
            return $referrerId;
        }

        if (preg_match('/(?:start=|startapp=|tgWebAppStartParam=)(ref_\d+)/', $value, $matches)) {
            return $this->parseReferrerId($matches[1]);
        }

        return null;
    }

    public function getSummary(User $user): array
    {
        $invitedCount = $this->referrals->countInvitedUsers($user);
        $activeReferralsCount = $this->countActiveReferrals($user);
        $permanentDiscountPercent = $this->resolvePermanentDiscountPercent($activeReferralsCount);
        $accumulatedDiscountPercent = (int) ($user->referral_accumulated_discount_percent ?? 0);
        $nextLevel = $this->resolveNextLevel($activeReferralsCount);

        return [
            'referral_link' => $this->buildReferralLink($user),
            'referral_code' => 'ref_'.$user->id,
            'has_referrer' => $user->referrer_id !== null,
            'can_claim' => $this->canClaimManually($user),
            'invited_count' => $invitedCount,
            'active_referrals_count' => $activeReferralsCount,
            'accumulated_discount_percent' => $accumulatedDiscountPercent,
            'permanent_discount_percent' => $permanentDiscountPercent,
            'total_discount_percent' => $accumulatedDiscountPercent + $permanentDiscountPercent,
            'next_level_active_referrals' => $nextLevel,
            'remaining_to_next_level' => $nextLevel === null ? 0 : max(0, $nextLevel - $activeReferralsCount),
        ];
    }

    public function buildReferralLink(User $user): ?string
    {
        $botUsername = trim((string) config('services.telegram.bot_username', ''));

        if ($botUsername === '') {
            return null;
        }

        return sprintf('https://t.me/%s?startapp=%s', ltrim($botUsername, '@'), 'ref_'.$user->id);
    }

    public function countActiveReferrals(User $user): int
    {
        return $this->referrals->countActiveReferrals($user);
    }

    public function canClaimManually(User $user): bool
    {
        if ($user->referrer_id !== null || empty($user->join_at)) {
            return false;
        }

        return Carbon::parse($user->join_at)->greaterThanOrEqualTo(now()->subMonth());
    }

    public function resolvePermanentDiscountPercent(int $activeReferralsCount): int
    {
        foreach (self::PERMANENT_DISCOUNT_LEVELS as $level) {
            if ($activeReferralsCount >= $level['from']) {
                return $level['percent'];
            }
        }

        return 0;
    }

    private function resolveNextLevel(int $activeReferralsCount): ?int
    {
        foreach (array_reverse(self::PERMANENT_DISCOUNT_LEVELS) as $level) {
            if ($activeReferralsCount < $level['from']) {
                return $level['from'];
            }
        }

        return null;
    }
}
