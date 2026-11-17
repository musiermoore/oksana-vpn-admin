<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SubscriptionType;
use Illuminate\Database\Eloquent\Collection;

final class SubscriptionTypeRepository
{
    /** @return Collection<int, SubscriptionType> */
    public function ordered(): Collection
    {
        return SubscriptionType::query()
            ->with('items')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function create(array $attributes): SubscriptionType
    {
        return SubscriptionType::query()->create($attributes);
    }

    public function update(SubscriptionType $type, array $attributes): SubscriptionType
    {
        $type->update($attributes);

        return $type->refresh();
    }

    /** @param array<int, array{source_type:string,source_id:int,sort_order:int}> $items */
    public function replaceItems(SubscriptionType $type, array $items): void
    {
        $type->items()->delete();
        $type->items()->createMany($items);
    }

    public function delete(SubscriptionType $type): void
    {
        $type->delete();
    }
}
