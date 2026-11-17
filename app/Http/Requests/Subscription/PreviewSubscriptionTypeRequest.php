<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use App\Http\Requests\Subscription\Concerns\ValidatesStaticSubscriptionSources;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PreviewSubscriptionTypeRequest extends FormRequest
{
    use ValidatesStaticSubscriptionSources;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(SubscriptionType::KINDS)],
            'format' => ['required', Rule::in(SubscriptionType::FORMATS)],
            'requires_user_token' => ['required', 'boolean'],
            'show_expired_placeholder' => ['required', 'boolean'],
            'items' => ['present', 'array', 'max:500'],
            'items.*' => ['required', 'array:source_type,source_id,sort_order'],
            'items.*.source_type' => ['required', 'string', Rule::in(SubscriptionTypeItem::SOURCES)],
            'items.*.source_id' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'audience' => [Rule::requiredIf(fn (): bool => $this->boolean('requires_user_token')), 'nullable', Rule::in(['active', 'admin', 'inactive', 'user'])],
            'user_id' => [
                Rule::requiredIf(fn (): bool => $this->boolean('requires_user_token') && $this->input('audience') === 'user'),
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            if (! $this->boolean('requires_user_token')) {
                $this->addStaticSourceErrors($validator, $items);

                if ($this->boolean('show_expired_placeholder')) {
                    $validator->errors()->add('show_expired_placeholder', 'Статическая ссылка не использует заглушку для пользователей без доступа.');
                }
            }

            foreach ($items as $index => $item) {
                if (
                    is_array($item)
                    && ($item['source_type'] ?? null) === SubscriptionTypeItem::SOURCE_XRAY_CUSTOM_CONFIG
                    && $this->input('format') !== SubscriptionType::FORMAT_JSON
                ) {
                    $validator->errors()->add("items.{$index}.source_type", 'Пользовательские профили Xray доступны только для JSON-формата.');
                }
            }
        });
    }
}
