<?php

declare(strict_types=1);

namespace App\Http\Requests\CurrentPayment;

use App\DTOs\CurrentPayment\CurrentPaymentData;
use App\Http\Requests\DataFormRequest;

class StoreCurrentPaymentRequest extends DataFormRequest
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

    protected function dtoClass(): string
    {
        return CurrentPaymentData::class;
    }
}
