<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use App\DTOs\Subscription\SubscriptionTypeData;
use App\Http\Requests\DataFormRequest;
use App\Http\Requests\Subscription\Concerns\ValidatesStaticSubscriptionSources;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SaveSubscriptionTypeRequest extends DataFormRequest
{
    use ValidatesStaticSubscriptionSources;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('subscription_types', 'slug')->ignore($this->route('subscriptionType')),
            ],
            'kind' => ['required', Rule::in(SubscriptionType::KINDS)],
            'format' => ['required', Rule::in(SubscriptionType::FORMATS)],
            'is_active' => ['required', 'boolean'],
            'requires_user_token' => ['required', 'boolean'],
            'show_expired_placeholder' => ['required', 'boolean'],
            'items' => ['present', 'array', 'max:500'],
            'items.*' => ['required', 'array:source_type,source_id,sort_order,is_free'],
            'items.*.source_type' => ['required', 'string', Rule::in(SubscriptionTypeItem::SOURCES)],
            'items.*.source_id' => ['required', 'integer', 'min:1'],
            'items.*.is_free' => ['sometimes', 'boolean'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];

        return $rules;
    }

    protected function laravelData(): string
    {
        return SubscriptionTypeData::class;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);
            $this->addMissingSourceErrors($validator, $items, SubscriptionTypeItem::SOURCE_SERVER, 'servers');
            $this->addMissingSourceErrors($validator, $items, SubscriptionTypeItem::SOURCE_PROXY, 'proxies');
            $this->addMissingSourceErrors($validator, $items, SubscriptionTypeItem::SOURCE_EXTERNAL_SUBSCRIPTION, 'vless_external_subscriptions');
            $this->addMissingSourceErrors($validator, $items, SubscriptionTypeItem::SOURCE_XRAY_CUSTOM_CONFIG, 'xray_custom_configs');
            if (! $this->boolean('requires_user_token')) {
                $this->addStaticSourceErrors($validator, $items);

                if ($this->boolean('show_expired_placeholder')) {
                    $validator->errors()->add('show_expired_placeholder', 'Статическая ссылка не использует заглушку для пользователей без доступа.');
                }
            }
            $seen = [];

            foreach (is_array($items) ? $items : [] as $index => $item) {
                if (! is_array($item) || ! isset($item['source_type'], $item['source_id'])) {
                    continue;
                }

                $key = $item['source_type'].':'.$item['source_id'];

                if (isset($seen[$key])) {
                    $validator->errors()->add("items.{$index}.source_id", 'Источник уже добавлен в этот тип подписки.');
                }

                $seen[$key] = true;

                if (
                    $item['source_type'] === SubscriptionTypeItem::SOURCE_XRAY_CUSTOM_CONFIG
                    && $this->input('format') !== SubscriptionType::FORMAT_JSON
                ) {
                    $validator->errors()->add("items.{$index}.source_type", 'Пользовательские профили Xray доступны только для JSON-формата.');
                }
            }
        });
    }

    private function addMissingSourceErrors(Validator $validator, mixed $items, string $sourceType, string $table): void
    {
        if (! is_array($items)) {
            return;
        }

        $sourceIds = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item)
                && ($item['source_type'] ?? null) === $sourceType
                && isset($item['source_id'])
                && is_numeric($item['source_id']))
            ->mapWithKeys(fn (array $item, int|string $index): array => [$index => (int) $item['source_id']]);

        if ($sourceIds->isEmpty()) {
            return;
        }

        $existingIds = DB::table($table)
            ->whereIn('id', $sourceIds->values()->all())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->flip();

        foreach ($sourceIds as $index => $sourceId) {
            if (! $existingIds->has((int) $sourceId)) {
                $validator->errors()->add("items.{$index}.source_id", 'Выбранный источник больше не существует.');
            }
        }
    }
}
