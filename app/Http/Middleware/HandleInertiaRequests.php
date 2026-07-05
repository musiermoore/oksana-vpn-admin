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
            ['label' => 'Инвойсы', 'href' => route('invoices.index'), 'badge' => 'IV'],
            ['label' => 'Подписки', 'href' => route('subscriptions.index'), 'badge' => 'SB'],
            ['label' => 'Периоды оплаты', 'href' => route('current-payments.index'), 'badge' => 'PP'],
            ['label' => 'Доп. оплаты', 'href' => route('extra-payments.index'), 'badge' => 'DP'],
            ['label' => 'Рефералка', 'href' => route('referrals.index'), 'badge' => 'RF'],
            ['label' => 'API лог', 'href' => route('api-request-logs.index'), 'badge' => 'LG'],
            ['label' => '3x-ui Debug', 'href' => route('xui-debug.index'), 'badge' => 'XD'],
            ['label' => 'Tax Debug', 'href' => route('tax-debug.index'), 'badge' => 'TD'],
            ['label' => 'Tax Settings', 'href' => route('tax-settings.edit'), 'badge' => 'TS'],
            ['label' => 'Поддержка', 'href' => route('support-tickets.index'), 'badge' => 'SP'],
            ['label' => 'Рассылка', 'href' => route('notifications.create'), 'badge' => 'NT'],
            ['label' => 'Welcome', 'href' => route('messages.welcome.edit'), 'badge' => 'WM'],
            ['label' => 'Сервера', 'href' => route('servers.index'), 'badge' => 'SV'],
            ['label' => 'Прокси', 'href' => route('proxies.index'), 'badge' => 'PX'],
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
