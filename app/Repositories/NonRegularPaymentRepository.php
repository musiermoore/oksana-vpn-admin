<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\NonRegularPayment;
use Illuminate\Database\Eloquent\Collection;
use Carbon\CarbonImmutable;

class NonRegularPaymentRepository
{
    /** @return Collection<int, NonRegularPayment> */
    public function latest(): Collection
    {
        return NonRegularPayment::query()->latest()->get();
    }

    /** @param array{amount: float, description: string} $attributes */
    public function create(array $attributes): NonRegularPayment
    {
        return NonRegularPayment::query()->create($attributes);
    }

    public function deleteById(string|int $id): void
    {
        NonRegularPayment::query()->whereKey($id)->delete();
    }

    public function totalForRange(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) NonRegularPayment::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('amount');
    }
}
