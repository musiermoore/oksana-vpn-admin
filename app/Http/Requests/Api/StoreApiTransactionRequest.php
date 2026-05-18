<?php

namespace App\Http\Requests\Api;

use App\DTOs\Transaction\ApiDepositTransactionData;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'bank' => ['required', 'string', 'max:255'],
        ];
    }

    public function toDto(): ApiDepositTransactionData
    {
        $data = $this->validated();

        return new ApiDepositTransactionData(
            amount: (float) $data['amount'],
            bank: trim($data['bank']),
        );
    }
}
