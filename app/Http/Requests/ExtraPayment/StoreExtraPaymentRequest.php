<?php

declare(strict_types=1);

namespace App\Http\Requests\ExtraPayment;

use App\DTOs\ExtraPayment\ExtraPaymentData;
use App\Http\Requests\DataFormRequest;

class StoreExtraPaymentRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'current_payment_id' => ['required', 'exists:current_payments,id'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function dtoClass(): string
    {
        return ExtraPaymentData::class;
    }
}
