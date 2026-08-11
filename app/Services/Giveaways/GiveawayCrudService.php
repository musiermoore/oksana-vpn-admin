<?php

declare(strict_types=1);

namespace App\Services\Giveaways;

use App\DTOs\Giveaway\GiveawayData;
use App\Models\Giveaway;
use App\Models\GiveawaySeries;
use App\Repositories\GiveawayPrizeRepository;
use App\Repositories\GiveawayRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GiveawayCrudService
{
    public function __construct(
        private readonly GiveawayRepository $giveaways,
        private readonly GiveawayPrizeRepository $prizes,
    ) {}

    public function create(GiveawayData $data): Giveaway
    {
        return DB::transaction(function () use ($data): Giveaway {
            $startsAt = Carbon::parse($data->startsAt);
            $endsAt = Carbon::parse($data->endsAt);

            $series = GiveawaySeries::query()->create([
                'name' => $data->title,
                'auto_repeat_enabled' => $data->autoRepeatEnabled,
                'repeat_delay_minutes' => $data->repeatDelayMinutes,
                'repeat_limit' => $data->repeatLimit,
            ]);

            $giveaway = $this->giveaways->create([
                'series_id' => $series->id,
                'sequence_number' => 1,
                'title' => $data->title,
                'description' => $data->description,
                'status' => Giveaway::STATUS_DRAFT,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $startsAt->diffInMinutes($endsAt),
            ]);

            $this->syncPrizes($giveaway, $data);

            return $giveaway->refresh()->load('series', 'prizes');
        });
    }

    public function update(Giveaway $giveaway, GiveawayData $data): Giveaway
    {
        return DB::transaction(function () use ($giveaway, $data): Giveaway {
            $startsAt = Carbon::parse($data->startsAt);
            $endsAt = Carbon::parse($data->endsAt);

            $this->giveaways->update($giveaway, [
                'title' => $data->title,
                'description' => $data->description,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $startsAt->diffInMinutes($endsAt),
            ]);

            $giveaway->series?->update([
                'name' => $data->title,
                'auto_repeat_enabled' => $data->autoRepeatEnabled,
                'repeat_delay_minutes' => $data->repeatDelayMinutes,
                'repeat_limit' => $data->repeatLimit,
            ]);

            $this->syncPrizes($giveaway->refresh(), $data);

            return $giveaway->refresh()->load('series', 'prizes');
        });
    }

    public function activate(Giveaway $giveaway): Giveaway
    {
        if ($giveaway->prizes()->where('quantity', '>', 0)->doesntExist()) {
            throw new \DomainException('Нельзя активировать розыгрыш без призов.');
        }

        if ($giveaway->starts_at->gte($giveaway->ends_at)) {
            throw new \DomainException('Дата окончания должна быть позже даты начала.');
        }

        return $this->giveaways->update($giveaway, [
            'status' => $giveaway->starts_at->lte(now()) ? Giveaway::STATUS_ACTIVE : Giveaway::STATUS_SCHEDULED,
        ]);
    }

    public function cancel(Giveaway $giveaway): Giveaway
    {
        return $this->giveaways->update($giveaway, [
            'status' => Giveaway::STATUS_CANCELLED,
        ]);
    }

    private function syncPrizes(Giveaway $giveaway, GiveawayData $data): void
    {
        if (! $giveaway->canEditPrizes()) {
            return;
        }

        $rows = [];

        foreach ($data->prizes as $index => $prize) {
            if ($prize->quantity < 0) {
                continue;
            }

            $rows[] = [
                'duration_months' => $prize->durationMonths,
                'quantity' => $prize->quantity,
                'title' => $prize->title,
                'sort_order' => $index,
            ];
        }

        $this->prizes->replaceForGiveaway($giveaway, $rows);
    }
}
