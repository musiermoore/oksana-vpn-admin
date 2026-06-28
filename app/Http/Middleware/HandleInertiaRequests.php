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
            ['label' => 'WireGuard', 'href' => route('configs.index'), 'badge' => 'WG'],
            ['label' => 'Xray Configs', 'href' => route('xray-configs.index'), 'badge' => 'XR'],
            ['label' => 'Транзакции', 'href' => route('transactions.index'), 'badge' => 'TR'],
            ['label' => 'Подписки', 'href' => route('subscriptions.index'), 'badge' => 'SB'],
            ['label' => 'Периоды оплаты', 'href' => route('current-payments.index'), 'badge' => 'PP'],
            ['label' => 'Доп. оплаты', 'href' => route('extra-payments.index'), 'badge' => 'DP'],
            ['label' => 'Рефералка', 'href' => route('referrals.index'), 'badge' => 'RF'],
            ['label' => 'API лог', 'href' => route('api-request-logs.index'), 'badge' => 'LG'],
            ['label' => 'Поддержка', 'href' => route('support-tickets.index'), 'badge' => 'SP'],
            ['label' => 'Рассылка', 'href' => route('notifications.create'), 'badge' => 'NT'],
            ['label' => 'Welcome', 'href' => route('messages.welcome.edit'), 'badge' => 'WM'],
            ['label' => 'Сервера', 'href' => route('servers.index'), 'badge' => 'SV'],
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
