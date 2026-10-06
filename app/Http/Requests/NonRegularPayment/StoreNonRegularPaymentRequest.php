<?php

declare(strict_types=1);

namespace App\Http\Requests\NonRegularPayment;

use App\DTOs\NonRegularPayment\NonRegularPaymentData;
use App\Http\Requests\DataFormRequest;

class StoreNonRegularPaymentRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function laravelData(): string
    {
        return NonRegularPaymentData::class;
    }
}
