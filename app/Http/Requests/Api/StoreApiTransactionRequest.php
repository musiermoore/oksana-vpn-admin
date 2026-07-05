<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\DTOs\Transaction\ApiDepositTransactionData;
use App\Enums\SubscriptionPurchaseType;
use App\Http\Requests\DataFormRequest;
use Illuminate\Validation\Rule;

class StoreApiTransactionRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'in:0,1,3,6,12'],
            'return_url' => ['nullable', 'url', 'max:2048'],
            'purchase_type' => ['nullable', Rule::enum(SubscriptionPurchaseType::class)],
        ];
    }

    protected function dtoClass(): string
    {
        return ApiDepositTransactionData::class;
    }
}
