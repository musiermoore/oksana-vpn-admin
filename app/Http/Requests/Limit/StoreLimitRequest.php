<?php

namespace App\Http\Requests\Limit;

use App\DTOs\Limit\LimitData;
use Illuminate\Foundation\Http\FormRequest;

class StoreLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config_id' => ['required', 'exists:configs,id'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toDto(): LimitData
    {
        $data = $this->validated();

        return new LimitData(
            configId: (int) $data['config_id'],
            amount: (int) $data['amount'],
        );
    }
}
