<?php

namespace App\Http\Requests\Api;

use App\DTOs\Transaction\ApiDepositTransactionData;
use App\Enums\SubscriptionPurchaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTransactionRequest extends FormRequest
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

    public function toDto(): ApiDepositTransactionData
    {
        $data = $this->validated();

        return new ApiDepositTransactionData(
            month: (int) $data['month'],
            returnUrl: isset($data['return_url']) ? trim($data['return_url']) : null,
            purchaseType: isset($data['purchase_type'])
                ? SubscriptionPurchaseType::from((string) $data['purchase_type'])
                : SubscriptionPurchaseType::PERSONAL,
        );
    }
}
