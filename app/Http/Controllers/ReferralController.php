<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referrals,
    ) {}

    public function index(Request $request)
    {
        $referrers = User::query()
            ->whereHas('referralLinkages')
            ->withCount([
                'referrals',
                'activeReferrals',
            ])
            ->orderByDesc('referrals_count')
            ->orderBy('id')
            ->get()
            ->map(function (User $user): array {
                $summary = $this->referrals->getSummary($user);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'telegram' => $user->telegram,
                    'referral_link' => $summary['referral_link'],
                    'invited_count' => $summary['invited_count'],
                    'active_referrals_count' => $summary['active_referrals_count'],
                    'accumulated_discount_percent' => $summary['accumulated_discount_percent'],
                    'permanent_discount_percent' => $summary['permanent_discount_percent'],
                    'total_discount_percent' => $summary['total_discount_percent'],
                    'show_url' => route('referrals.show', $user),
                ];
            })
            ->values()
            ->all();

        return $this->inertia('Referrals/Index', [
            'referrers' => $referrers,
        ]);
    }

    public function show(Request $request, User $user)
    {
        $referrals = Referral::query()
            ->with(['referralUser', 'qualifyingTransaction'])
            ->where('referrer_id', $user->id)
            ->orderBy('created_at')
            ->get()
            ->map(function (Referral $referral): array {
                $referralUser = $referral->referralUser;
                $isActive = $referralUser !== null
                    && $referralUser->subscription_expires_at !== null
                    && $referralUser->subscription_expires_at->isFuture();

                return [
                    'id' => $referral->id,
                    'name' => $referralUser?->name,
                    'telegram' => $referralUser?->telegram,
                    'is_active' => $isActive,
                    'has_subscription' => $referralUser?->subscription_expires_at !== null,
                    'subscription_expires_at' => optional($referralUser?->subscription_expires_at)?->format('d.m.Y H:i'),
                    'reward_status' => $referral->reward_status,
                    'reward_percent' => (int) $referral->referrer_reward_percent,
                    'bonus_days' => (int) $referral->invitee_bonus_days,
                    'created_at' => optional($referral->created_at)?->format('d.m.Y H:i'),
                    'rewarded_at' => optional($referral->rewarded_at)?->format('d.m.Y H:i'),
                ];
            })
            ->values()
            ->all();

        return $this->inertia('Referrals/Show', [
            'referrer' => [
                'id' => $user->id,
                'name' => $user->name,
                'telegram' => $user->telegram,
                ...$this->referrals->getSummary($user),
            ],
            'referrals' => $referrals,
        ]);
    }
}
