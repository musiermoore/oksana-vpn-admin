<?php

declare(strict_types=1);

namespace App\Http\Requests\Transaction;

use App\DTOs\Transaction\TransactionData;
use App\Http\Requests\DataFormRequest;

class StoreTransactionRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'type_id' => ['required', 'exists:transaction_types,id'],
            'amount' => ['required', 'numeric'],
            'description' => ['nullable', 'string'],
            'is_approved' => ['nullable', 'boolean'],
        ];
    }

    protected function dtoClass(): string
    {
        return TransactionData::class;
    }
}
