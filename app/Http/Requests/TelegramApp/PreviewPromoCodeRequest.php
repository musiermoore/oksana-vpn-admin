<?php

declare(strict_types=1);

namespace App\Http\Requests\TelegramApp;

use App\DTOs\PromoCode\PreviewPromoCodeData;
use App\Http\Requests\DataFormRequest;

class PreviewPromoCodeRequest extends DataFormRequest
{
    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'in:1,3,6,12'],
            'promo_code' => ['bail', 'required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9_-]+\z/'],
        ];
    }

    protected function laravelData(): string
    {
        return PreviewPromoCodeData::class;
    }
}
