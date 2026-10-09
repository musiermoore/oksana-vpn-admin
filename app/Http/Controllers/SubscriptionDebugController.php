<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionDebug\StoreSubscriptionDebugRequest;
use App\Services\SubscriptionDebugService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Response as InertiaResponse;

final class SubscriptionDebugController extends Controller
{
    public function __construct(
        private readonly SubscriptionDebugService $subscriptions,
    ) {}

    public function index(Request $request): InertiaResponse|Response|RedirectResponse
    {
        $uuid = $request->query('uuid');

        if ($uuid !== null) {
            return $this->publicSubscription((string) $uuid);
        }

        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        return $this->inertia('SubscriptionDebug/Index', [
            'created' => session('subscription_debug_created'),
            'current' => $this->subscriptions->current(),
        ]);
    }

    public function store(StoreSubscriptionDebugRequest $request): RedirectResponse
    {
        $uuid = $this->subscriptions->store($request->toData());

        return redirect()
            ->route('subscription-debug.index')
            ->with('success', 'Подписка сохранена на один час.')
            ->with('subscription_debug_created', [
                'uuid' => $uuid,
                'url' => route('subscription-debug.index', ['uuid' => $uuid]),
                'expires_in' => $this->subscriptions->ttlSeconds(),
            ]);
    }

    private function publicSubscription(string $uuid): Response
    {
        $subscription = $this->subscriptions->body($uuid);

        return response($subscription['body'], 200, [
            'Content-Type' => $subscription['type'] === 'json'
                ? 'application/json; charset=UTF-8'
                : 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
