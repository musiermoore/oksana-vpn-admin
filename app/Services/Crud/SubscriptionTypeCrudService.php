<?php

declare(strict_types=1);

namespace App\Services\Crud;

use App\DTOs\Subscription\SubscriptionTypeData;
use App\Models\SubscriptionType;
use App\Repositories\SubscriptionTypeRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SubscriptionTypeCrudService
{
    public function __construct(private readonly SubscriptionTypeRepository $types) {}

    /** @return Collection<int, SubscriptionType> */
    public function ordered(): Collection
    {
        return $this->types->ordered();
    }

    public function create(SubscriptionTypeData $data): SubscriptionType
    {
        return DB::transaction(function () use ($data): SubscriptionType {
            $type = $this->types->create($data->toModelAttributes());
            $this->types->replaceItems($type, $this->normalizeItems($data->items));

            return $type->load('items');
        });
    }

    public function update(SubscriptionType $type, SubscriptionTypeData $data): SubscriptionType
    {
        return DB::transaction(function () use ($type, $data): SubscriptionType {
            $this->types->update($type, $data->toModelAttributes());
            $this->types->replaceItems($type, $this->normalizeItems($data->items));

            return $type->load('items');
        });
    }

    public function delete(SubscriptionType $type): void
    {
        if ($type->is_system) {
            throw new RuntimeException('Системный тип подписки нельзя удалить.');
        }

        $this->types->delete($type);
    }

    /** @param array<int, array{source_type:string,source_id:int,sort_order:int,is_free?:bool}> $items
     *  @return array<int, array{source_type:string,source_id:int,sort_order:int,is_free:bool}>
     */
    private function normalizeItems(array $items): array
    {
        $seen = [];

        return collect($items)
            ->map(function (array $item, int $index) use (&$seen): array {
                $sourceType = (string) $item['source_type'];
                $sourceId = (int) $item['source_id'];
                $key = $sourceType.':'.$sourceId;

                if (isset($seen[$key])) {
                    throw new RuntimeException('Источник добавлен в тип подписки больше одного раза.');
                }

                $seen[$key] = true;

                return [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                    'is_free' => (bool) ($item['is_free'] ?? false),
                ];
            })
            ->values()
            ->all();
    }
}
