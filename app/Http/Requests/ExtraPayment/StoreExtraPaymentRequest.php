<?php

namespace App\Http\Requests\ExtraPayment;

use App\DTOs\ExtraPayment\ExtraPaymentData;
use Illuminate\Foundation\Http\FormRequest;

class StoreExtraPaymentRequest extends FormRequest
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

    public function toDto(): ExtraPaymentData
    {
        $data = $this->validated();

        return new ExtraPaymentData(
            userId: (int) $data['user_id'],
            currentPaymentId: (int) $data['current_payment_id'],
            amount: (float) $data['amount'],
        );
    }
}
