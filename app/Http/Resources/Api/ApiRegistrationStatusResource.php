<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiRegistrationStatusResource extends JsonResource
{
    public function __construct($resource, private readonly bool $hasMoneyForNextSubscriptionMonth = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [
                'registered' => false,
                'active_subscription_end_date' => null,
                'has_money_for_next_subscription_month' => false,
            ];
        }

        return [
            'registered' => true,
            'active_subscription_end_date' => $this->latestActiveOrFutureSubscription?->end_date,
            'has_money_for_next_subscription_month' => $this->hasMoneyForNextSubscriptionMonth,
        ];
    }
}
