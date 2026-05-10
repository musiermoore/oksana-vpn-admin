<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        $navigation = $user ? [
            ['label' => 'Участники', 'href' => route('users.index'), 'badge' => 'US'],
            ['label' => 'Конфиги', 'href' => route('configs.index'), 'badge' => 'CF'],
            ['label' => 'VLESS', 'href' => route('vless-configs.index'), 'badge' => 'VL'],
            ['label' => 'Транзакции', 'href' => route('transactions.index'), 'badge' => 'TX'],
            ['label' => 'Подписки', 'href' => route('subscriptions.index'), 'badge' => 'SB'],
            ['label' => 'Периоды оплаты', 'href' => route('current-payments.index'), 'badge' => 'PP'],
            ['label' => 'Доп. оплаты', 'href' => route('extra-payments.index'), 'badge' => 'DP'],
            ['label' => 'Рассылка', 'href' => route('notifications.create'), 'badge' => 'NT'],
            ['label' => 'Серверы', 'href' => route('servers.index'), 'badge' => 'SV'],
        ] : [];

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name', 'VPN Admin'),
                'isAuthorized' => (bool) $request->attributes->get('isAuthorized'),
                'currentPath' => $request->path(),
                'navigation' => $navigation,
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'telegram' => $user->telegram,
                    'is_admin' => (bool) $user->is_admin,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
