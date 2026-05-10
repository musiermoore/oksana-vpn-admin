<?php

namespace App\Http\Resources;

use App\Models\UserSubscription;

class UserSubscriptionResource
{
    public static function make(UserSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date,
            'price' => (float) $subscription->price,
            'is_active' => now()->betweenIncluded($subscription->start_date, $subscription->end_date),
            'user' => $subscription->user ? [
                'id' => $subscription->user->id,
                'full_name' => $subscription->user->full_name,
                'telegram' => $subscription->user->telegram,
                'is_active' => $subscription->user->is_active,
                'edit_url' => route('users.edit', $subscription->user),
            ] : null,
            'links' => [
                'edit' => route('subscriptions.edit', $subscription),
            ],
        ];
    }
}
