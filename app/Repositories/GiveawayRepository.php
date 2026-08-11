<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Giveaway;
use Illuminate\Database\Eloquent\Collection;

class GiveawayRepository
{
    public function create(array $attributes): Giveaway
    {
        return Giveaway::query()->create($attributes);
    }

    public function update(Giveaway $giveaway, array $attributes): Giveaway
    {
        $giveaway->update($attributes);

        return $giveaway->refresh();
    }

    public function findWithRelations(int $id): ?Giveaway
    {
        return Giveaway::query()
            ->with(['series', 'prizes', 'participants.user', 'winners.user', 'winners.prize'])
            ->find($id);
    }

    public function lockById(int $id): ?Giveaway
    {
        return Giveaway::query()
            ->with(['series', 'prizes'])
            ->lockForUpdate()
            ->find($id);
    }

    public function dueScheduled(): Collection
    {
        return Giveaway::query()
            ->where('status', Giveaway::STATUS_SCHEDULED)
            ->where('starts_at', '<=', now())
            ->get();
    }

    public function dueForDraw(): Collection
    {
        return Giveaway::query()
            ->whereIn('status', [Giveaway::STATUS_ACTIVE, Giveaway::STATUS_DRAWING])
            ->where('ends_at', '<=', now())
            ->orderBy('ends_at')
            ->get();
    }

    public function visible(): ?Giveaway
    {
        return Giveaway::query()
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

    public function nextInSeries(int $seriesId, int $sequenceNumber): ?Giveaway
    {
        return Giveaway::query()
            ->where('series_id', $seriesId)
            ->where('sequence_number', $sequenceNumber)
            ->first();
    }
}
