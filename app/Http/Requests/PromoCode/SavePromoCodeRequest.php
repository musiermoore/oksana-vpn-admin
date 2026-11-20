<?php

declare(strict_types=1);

namespace App\Http\Requests\PromoCode;

use App\DTOs\PromoCode\PromoCodeData;
use App\Enums\PromoCodeBenefit;
use App\Http\Requests\DataFormRequest;
use App\Models\PromoCode;
use Illuminate\Validation\Rule;

class SavePromoCodeRequest extends DataFormRequest
{
    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => PromoCode::normalize($code)]);
        }
    }

    public function rules(): array
    {
        $promoCode = $this->route('promo_code');
        $unique = Rule::unique('promo_codes', 'code');

        if ($promoCode instanceof PromoCode) {
            $unique->ignore($promoCode);
        }

        return [
            'code' => ['required', 'string', 'max:64', 'regex:/\A[A-Z0-9_-]+\z/', $unique],
            'benefit' => ['required', Rule::enum(PromoCodeBenefit::class)],
            'value' => $this->valueRules(),
            'once_per_user' => ['sometimes', 'boolean'],
            'new_users_only' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => [
                'nullable', 'date_format:Y-m-d',
                Rule::when($this->filled('starts_on'), 'after_or_equal:starts_on'),
            ],
        ];
    }

    private function valueRules(): array
    {
        return match ($this->input('benefit')) {
            PromoCodeBenefit::BonusDays->value => ['required', 'integer', 'min:1', 'max:36500'],
            PromoCodeBenefit::PercentageDiscount->value => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100'],
            default => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
        };
    }

    protected function laravelData(): string
    {
        return PromoCodeData::class;
    }
}
