<?php

declare(strict_types=1);

namespace App\Services\Giveaways;

use App\Models\Giveaway;
use App\Models\User;
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

    public function visible(): ?Giveaway
    {
        $this->syncLifecycleStates();

        return $this->giveaways->visible();
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
        if (! $giveaway->canParticipate()) {
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
}
