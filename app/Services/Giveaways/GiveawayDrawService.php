<?php

declare(strict_types=1);

namespace App\Services\Giveaways;

use App\Models\Giveaway;
use App\Models\GiveawayWinner;
use App\Repositories\GiveawayParticipantRepository;
use App\Repositories\GiveawayRepository;
use App\Repositories\GiveawayWinnerRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

class GiveawayDrawService
{
    public function __construct(
        private readonly GiveawayRepository $giveaways,
        private readonly GiveawayParticipantRepository $participants,
        private readonly GiveawayWinnerRepository $winners,
        private readonly GiveawayEligibilityService $eligibility,
        private readonly WeightedWinnerSelector $selector,
        private readonly GiveawayGrantService $grantService,
        private readonly GiveawayRepeatService $repeatService,
    ) {}

    public function draw(Giveaway $giveaway): Giveaway
    {
        return DB::transaction(function () use ($giveaway): Giveaway {
            $lockedGiveaway = $this->giveaways->lockById($giveaway->id);

            if (! $lockedGiveaway) {
                throw new \RuntimeException('Розыгрыш не найден.');
            }

            if (in_array($lockedGiveaway->status, [Giveaway::STATUS_FINISHED, Giveaway::STATUS_CANCELLED], true)) {
                return $lockedGiveaway;
            }

            if (! in_array($lockedGiveaway->status, [Giveaway::STATUS_ACTIVE, Giveaway::STATUS_DRAWING], true)) {
                throw new \DomainException('Определять победителей можно только у активного розыгрыша.');
            }

            $lockedGiveaway->update([
                'status' => Giveaway::STATUS_DRAWING,
            ]);

            $participantPool = [];

            foreach ($this->participants->forGiveaway($lockedGiveaway) as $participant) {
                if (! $participant->user) {
                    continue;
                }

                $weight = $this->eligibility->calculateWeight($lockedGiveaway, $participant->user);

                $participant->update([
                    'weight_at_draw' => $weight->totalWeight,
                    'eligible_referrals_count_at_draw' => $weight->eligibleReferrals,
                    'snapshot_taken_at' => now(),
                ]);

                $participantPool[] = [
                    'participant_id' => $participant->id,
                    'user_id' => $participant->user_id,
                    'weight' => $weight->totalWeight,
                    'eligible_referrals_count' => $weight->eligibleReferrals,
                ];
            }

            $prizeSlots = $this->buildPrizeSlots($lockedGiveaway);

            foreach ($prizeSlots as $slot) {
                $winner = $this->selector->pick($participantPool);

                if ($winner === null) {
                    break;
                }

                $winnerModel = $this->winners->create([
                    'giveaway_id' => $lockedGiveaway->id,
                    'user_id' => $winner['user_id'],
                    'giveaway_prize_id' => $slot['giveaway_prize_id'],
                    'prize_slot' => $slot['prize_slot'],
                    'duration_months' => $slot['duration_months'],
                    'weight_at_draw' => $winner['weight'],
                    'eligible_referrals_count_at_draw' => $winner['eligible_referrals_count'],
                    'selected_at' => now(),
                    'prize_status' => GiveawayWinner::PRIZE_STATUS_PENDING,
                ]);

                $this->grantWinnerPrize($winnerModel);

                $participantPool = array_values(array_filter(
                    $participantPool,
                    fn (array $candidate): bool => $candidate['user_id'] !== $winner['user_id'],
                ));
            }

            $lockedGiveaway->update([
                'status' => Giveaway::STATUS_FINISHED,
            ]);

            $finishedGiveaway = $lockedGiveaway->refresh()->load(['series', 'prizes', 'winners.user', 'winners.prize']);
            $this->repeatService->createNextIfNeeded($finishedGiveaway);

            return $finishedGiveaway;
        });
    }

    /**
     * @return array<int, array{giveaway_prize_id:int,prize_slot:int,duration_months:int}>
     */
    private function buildPrizeSlots(Giveaway $giveaway): array
    {
        $slots = [];
        $slotNumber = 1;

        foreach ($giveaway->prizes as $prize) {
            for ($index = 0; $index < $prize->quantity; $index += 1) {
                $slots[] = [
                    'giveaway_prize_id' => $prize->id,
                    'prize_slot' => $slotNumber,
                    'duration_months' => $prize->duration_months,
                ];
                $slotNumber += 1;
            }
        }

        return $slots;
    }

    private function grantWinnerPrize(GiveawayWinner $winner): void
    {
        try {
            $winner->loadMissing('user');

            if (! $winner->user) {
                throw new \RuntimeException('Пользователь победителя не найден.');
            }

            $this->grantService->grant($winner, $winner->user);

            $this->winners->update($winner, [
                'prize_status' => GiveawayWinner::PRIZE_STATUS_GRANTED,
                'prize_granted_at' => now(),
                'prize_error' => null,
            ]);
        } catch (Throwable $exception) {
            $this->winners->update($winner, [
                'prize_status' => GiveawayWinner::PRIZE_STATUS_FAILED,
                'prize_error' => $exception->getMessage(),
            ]);
        }
    }
}
