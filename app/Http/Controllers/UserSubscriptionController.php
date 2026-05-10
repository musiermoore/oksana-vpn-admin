<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserSubscriptionResource;
use App\Http\Requests\UserSubscription\UpdateUserSubscriptionRequest;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $showOld = $request->boolean('old');
        $today = today()->toDateString();

        $subscriptions = UserSubscription::query()
            ->with([
                'user' => fn ($query) => $query->withTrashed(),
            ])
            ->when(
                $showOld,
                fn (Builder $query) => $query
                    ->whereDate('end_date', '<', $today)
                    ->orderByDesc('end_date')
                    ->orderByDesc('id'),
                fn (Builder $query) => $query
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->orderBy('end_date')
                    ->orderBy('id')
            )
            ->get();

        return $this->inertia('Subscriptions/Index', [
            'filters' => [
                'old' => $showOld,
            ],
            'subscriptions' => UserSubscriptionResource::collection($subscriptions),
        ]);
    }

    public function edit(UserSubscription $subscription)
    {
        $subscription->load([
            'user' => fn ($query) => $query->withTrashed(),
        ]);

        return $this->inertia('Subscriptions/Form', [
            'submit_url' => route('subscriptions.update', $subscription),
            'subscription' => new UserSubscriptionResource($subscription),
        ]);
    }

    public function update(UpdateUserSubscriptionRequest $request, UserSubscription $subscription)
    {
        $subscription->update($request->validated());

        return redirect()->route('subscriptions.index');
    }
}
