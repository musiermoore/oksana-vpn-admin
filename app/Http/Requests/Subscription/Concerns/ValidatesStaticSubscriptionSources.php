<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription\Concerns;

use App\Models\SubscriptionTypeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

trait ValidatesStaticSubscriptionSources
{
    private function addStaticSourceErrors(Validator $validator, mixed $items): void
    {
        if (! is_array($items)) {
            return;
        }

        $externalIds = [];
        $freeOverrides = [];

        foreach ($items as $index => $item) {
            if (! is_array($item) || ! isset($item['source_type'], $item['source_id'])) {
                continue;
            }

            if ($item['source_type'] !== SubscriptionTypeItem::SOURCE_EXTERNAL_SUBSCRIPTION) {
                $validator->errors()->add("items.{$index}.source_type", 'Статическая ссылка может содержать только бесплатные внешние подписки.');
                continue;
            }

            $externalIds[$index] = (int) $item['source_id'];
            $freeOverrides[$index] = (bool) ($item['is_free'] ?? false);
        }

        if ($externalIds === []) {
            $validator->errors()->add('items', 'Добавьте хотя бы одну бесплатную внешнюю подписку для статической ссылки.');

            return;
        }

        $sourceFreeFlags = DB::table('vless_external_subscriptions')
            ->whereIn('id', array_values($externalIds))
            ->pluck('is_free', 'id');

        foreach ($externalIds as $index => $sourceId) {
            if ($sourceFreeFlags->has($sourceId) && ($sourceFreeFlags->get($sourceId) || $freeOverrides[$index])) {
                continue;
            }

            $validator->errors()->add("items.{$index}.source_id", 'Статическая ссылка может содержать только бесплатные внешние подписки.');
        }
    }
}
