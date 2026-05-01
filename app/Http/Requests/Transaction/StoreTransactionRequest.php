<?php

namespace App\Http\Requests\Transaction;

use App\DTOs\Transaction\TransactionData;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
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

    public function toDto(): TransactionData
    {
        $data = $this->validated();

        return new TransactionData(
            userId: (int) $data['user_id'],
            typeId: (int) $data['type_id'],
            amount: (float) $data['amount'],
            description: $data['description'] ?? null,
            isApproved: (bool) ($data['is_approved'] ?? false),
        );
    }
}
