<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $navigation = [
            ['label' => 'Подключения', 'href' => route('wireguard.active-peers'), 'badge' => 'WG'],
            ['label' => 'Трафик', 'href' => route('wireguard.traffic'), 'badge' => 'TR'],
            ['label' => 'Участники', 'href' => route('users.index'), 'badge' => 'US'],
            ['label' => 'Токены', 'href' => route('user-tokens.index'), 'badge' => 'TK'],
            ['label' => 'Конфиги', 'href' => route('configs.index'), 'badge' => 'CF'],
            ['label' => 'VLESS', 'href' => route('vless-configs.index'), 'badge' => 'VL'],
            ['label' => 'Транзакции', 'href' => route('transactions.index'), 'badge' => 'TX'],
            ['label' => 'Периоды оплаты', 'href' => route('current-payments.index'), 'badge' => 'PP'],
            ['label' => 'Доп. оплаты', 'href' => route('extra-payments.index'), 'badge' => 'DP'],
            ['label' => 'Серверы', 'href' => route('servers.index'), 'badge' => 'SV'],
            ['label' => 'Ограничения', 'href' => route('limits.index'), 'badge' => 'LM'],
        ];

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name', 'VPN Admin'),
                'isAuthorized' => true,
                'currentPath' => $request->path(),
                'navigation' => $navigation,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
