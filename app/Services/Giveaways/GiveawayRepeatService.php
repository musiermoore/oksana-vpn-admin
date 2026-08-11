<?php

declare(strict_types=1);

namespace App\Services\Giveaways;

use App\Models\Giveaway;
use App\Models\GiveawaySeries;
use App\Repositories\GiveawayPrizeRepository;
use App\Repositories\GiveawayRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GiveawayRepeatService
{
    public function __construct(
        private readonly GiveawayRepository $giveaways,
        private readonly GiveawayPrizeRepository $prizes,
    ) {}

    public function createNextIfNeeded(Giveaway $giveaway): ?Giveaway
    {
        $series = $giveaway->series;

        if (! $series instanceof GiveawaySeries || ! $series->auto_repeat_enabled) {
            return null;
        }

        $nextSequenceNumber = $giveaway->sequence_number + 1;

        if ($series->repeat_limit !== null && $nextSequenceNumber > ($series->repeat_limit + 1)) {
            return null;
        }

        return DB::transaction(function () use ($giveaway, $series, $nextSequenceNumber): ?Giveaway {
            $existing = $this->giveaways->nextInSeries($series->id, $nextSequenceNumber);

            if ($existing) {
                return $existing;
            }

            $giveaway->loadMissing('prizes');

            if ($giveaway->prizes->where('quantity', '>', 0)->isEmpty()) {
                return null;
            }

            $startsAt = Carbon::parse($giveaway->ends_at)->addMinutes($series->repeat_delay_minutes);
            $endsAt = $startsAt->copy()->addMinutes($giveaway->duration_minutes);

            $nextGiveaway = $this->giveaways->create([
                'series_id' => $series->id,
                'parent_giveaway_id' => $giveaway->id,
                'sequence_number' => $nextSequenceNumber,
                'title' => $giveaway->title,
                'description' => $giveaway->description,
                'status' => $startsAt->lte(now()) ? Giveaway::STATUS_ACTIVE : Giveaway::STATUS_SCHEDULED,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $giveaway->duration_minutes,
            ]);

            $this->prizes->replaceForGiveaway(
                $nextGiveaway,
                $giveaway->prizes
                    ->map(fn ($prize, $index) => [
                        'duration_months' => $prize->duration_months,
                        'quantity' => $prize->quantity,
                        'title' => $prize->title,
                        'sort_order' => $index,
                    ])
                    ->all()
            );

            return $nextGiveaway->refresh();
        });
    }
}
