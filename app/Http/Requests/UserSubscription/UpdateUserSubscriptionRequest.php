<?php

declare(strict_types=1);

namespace App\Http\Requests\UserSubscription;

use App\DTOs\UserSubscription\UserSubscriptionUpdateData;
use App\Http\Requests\DataFormRequest;

class UpdateUserSubscriptionRequest extends DataFormRequest
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
        ];
    }

    protected function dtoClass(): string
    {
        return UserSubscriptionUpdateData::class;
    }
}
