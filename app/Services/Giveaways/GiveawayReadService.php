<?php

declare(strict_types=1);

namespace App\Services\Giveaways;

use App\Models\Giveaway;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\GiveawayParticipantRepository;
use App\Repositories\GiveawayRepository;

class GiveawayReadService
{
    public function __construct(
        private readonly GiveawayRepository $giveaways,
        private readonly GiveawayParticipantRepository $participants,
        private readonly GiveawayEligibilityService $eligibility,
    ) {}

    public function syncLifecycleStates(): void
    {
        foreach ($this->giveaways->dueScheduled() as $giveaway) {
            $giveaway->update(['status' => Giveaway::STATUS_ACTIVE]);
        }
    }

    public function visible(?User $user = null): ?Giveaway
    {
        $this->syncLifecycleStates();

        return $this->visibleQueryForUser($user)
            ->with(['prizes', 'winners.user', 'winners.prize'])
            ->orderByRaw("
                case
                    when status = 'active' then 0
                    when status = 'drawing' then 1
                    when status = 'scheduled' then 2
                    when status = 'finished' then 3
                    else 4
                end
            ")
            ->orderBy('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{active_giveaways_count:int,pending_participation_count:int}
     */
    public function summaryForUser(User $user): array
    {
        $this->syncLifecycleStates();

        $activeGiveaways = $this->visibleQueryForUser($user)
            ->where('status', Giveaway::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->get(['id']);

        if ($activeGiveaways->isEmpty()) {
            return [
                'active_giveaways_count' => 0,
                'pending_participation_count' => 0,
            ];
        }

        $activeIds = $activeGiveaways->pluck('id');

        $participatingIds = $user->giveawayParticipants()
            ->whereIn('giveaway_id', $activeIds)
            ->pluck('giveaway_id');

        return [
            'active_giveaways_count' => $activeIds->count(),
            'pending_participation_count' => $activeIds->diff($participatingIds)->count(),
        ];
    }

    /**
     * @return array{is_participant:bool,base_votes:int,eligible_referrals:int,total_weight:int}|null
     */
    public function participantState(Giveaway $giveaway, User $user): ?array
    {
        $participant = $giveaway->participants()
            ->where('user_id', $user->id)
            ->first();

        if (! $participant) {
            return null;
        }

        if ($giveaway->status === Giveaway::STATUS_FINISHED && $participant->weight_at_draw !== null) {
            return [
                'is_participant' => true,
                'base_votes' => 1,
                'eligible_referrals' => (int) $participant->eligible_referrals_count_at_draw,
                'total_weight' => (int) $participant->weight_at_draw,
            ];
        }

        $weight = $this->eligibility->calculateWeight($giveaway, $user);

        return [
            'is_participant' => true,
            'base_votes' => $weight->baseVotes,
            'eligible_referrals' => $weight->eligibleReferrals,
            'total_weight' => $weight->totalWeight,
        ];
    }

    public function participate(Giveaway $giveaway, User $user): array
    {
        $visibleGiveaway = $this->visible($user);

        if (! $visibleGiveaway || $visibleGiveaway->id !== $giveaway->id || ! $giveaway->canParticipate()) {
            throw new \DomainException('Сейчас участие в этом розыгрыше недоступно.');
        }

        $this->participants->firstOrCreate($giveaway->id, $user->id);

        return $this->participantState($giveaway->refresh(), $user) ?? [
            'is_participant' => false,
            'base_votes' => 0,
            'eligible_referrals' => 0,
            'total_weight' => 0,
        ];
    }

    private function visibleQueryForUser(?User $user): Builder
    {
        return Giveaway::query()
            ->when(
                ! $user?->is_admin,
                fn (Builder $query) => $query->where('admins_only', false)
            );
    }
}
