<?php

namespace App\Http\Requests\CurrentPayment;

use App\DTOs\CurrentPayment\CurrentPaymentData;
use Illuminate\Foundation\Http\FormRequest;

class StoreCurrentPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function toDto(): CurrentPaymentData
    {
        $data = $this->validated();

        return new CurrentPaymentData(
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            amount: (float) $data['amount'],
        );
    }
}
