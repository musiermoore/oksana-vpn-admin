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
            [
                    'section' => 'Обзор',
                    'icon' => 'dashboard',
                    'items' => [
                        ['label' => 'Панель', 'href' => route('dashboard.index'), 'badge' => 'PN', 'icon' => 'dashboard'],
                        ['label' => 'Отчеты', 'href' => route('reports.index'), 'badge' => 'RP', 'icon' => 'chart'],
                    ],
                ],
            [
                'section' => 'Сеть',
                'icon' => 'server',
                'items' => [
                    ['label' => 'Сервера', 'href' => route('servers.index'), 'badge' => 'SV', 'icon' => 'server'],
                    ['label' => 'Xray Configs', 'href' => route('xray-configs.index'), 'badge' => 'XR', 'icon' => 'nodes'],
                    ['label' => 'Прокси', 'href' => route('proxies.index'), 'badge' => 'PX', 'icon' => 'link'],
                    ['label' => 'VLESS WL', 'href' => route('vless-external-subscriptions.index'), 'badge' => 'VW', 'icon' => 'spark'],
                ],
            ],
            [
                'section' => 'Пользователи',
                'icon' => 'users',
                'items' => [
                    ['label' => 'Участники', 'href' => route('users.index'), 'badge' => 'US', 'icon' => 'users'],
                    ['label' => 'Рефералка', 'href' => route('referrals.index'), 'badge' => 'RF', 'icon' => 'gift'],
                    ['label' => 'Розыгрыши', 'href' => route('giveaways.index'), 'badge' => 'GW', 'icon' => 'gift'],
                    ['label' => 'Поддержка', 'href' => route('support-tickets.index'), 'badge' => 'SP', 'icon' => 'chat'],
                ],
            ],
            [
                'section' => 'Биллинг',
                'icon' => 'wallet',
                'items' => [
                    ['label' => 'Транзакции', 'href' => route('transactions.index'), 'badge' => 'TR', 'icon' => 'wallet'],
                    ['label' => 'Инвойсы', 'href' => route('invoices.index'), 'badge' => 'IV', 'icon' => 'receipt'],
                    ['label' => 'Подписки', 'href' => route('subscriptions.index'), 'badge' => 'SB', 'icon' => 'calendar'],
                    ['label' => 'Периоды оплаты', 'href' => route('current-payments.index'), 'badge' => 'PP', 'icon' => 'clock'],
                    ['label' => 'Налоги', 'href' => route('tax-settings.edit'), 'badge' => 'TX', 'icon' => 'stamp'],
                ],
            ],
            [
                'section' => 'Операции',
                'icon' => 'tasks',
                'items' => [
                    ['label' => 'Задачи', 'href' => route('tasks.index'), 'badge' => 'TD', 'icon' => 'tasks'],
                    ['label' => 'Рассылка', 'href' => route('notifications.create'), 'badge' => 'NT', 'icon' => 'send'],
                    ['label' => 'Welcome', 'href' => route('messages.welcome.edit'), 'badge' => 'WM', 'icon' => 'message'],
                ],
            ],
            [
                'section' => 'Диагностика',
                'icon' => 'activity',
                'items' => [
                    ['label' => 'API лог', 'href' => route('api-request-logs.index'), 'badge' => 'LG', 'icon' => 'log'],
                    ['label' => '3x-ui Debug', 'href' => route('xui-debug.index'), 'badge' => 'XD', 'icon' => 'terminal'],
                    ['label' => 'Tax Debug', 'href' => route('tax-debug.index'), 'badge' => 'TD', 'icon' => 'bug'],
                ],
            ],
        ] : [];

        $currentSection = null;
        $currentItem = null;
        $currentPath = '/'.ltrim($request->path(), '/');

        foreach ($navigation as $section) {
            foreach ($section['items'] as $item) {
                $itemPath = parse_url($item['href'], PHP_URL_PATH) ?: '/';

                if ($itemPath === $currentPath || ($itemPath !== '/' && str_starts_with($currentPath, $itemPath.'/'))) {
                    $currentSection = [
                        'label' => $section['section'],
                        'icon' => $section['icon'] ?? null,
                    ];
                    $currentItem = $item;
                    break 2;
                }
            }
        }

        $breadcrumbs = [];

        $dashboardPath = parse_url(route('dashboard.index'), PHP_URL_PATH) ?: '/';

        if ($currentPath === $dashboardPath) {
            $breadcrumbs[] = [
                'label' => 'Главная',
                'href' => route('dashboard.index'),
                'icon' => 'dashboard',
            ];
        } elseif ($currentSection !== null) {
            $breadcrumbs[] = [
                'label' => 'Главная',
                'href' => route('dashboard.index'),
                'icon' => 'dashboard',
            ];
            $breadcrumbs[] = $currentSection;
        }

        if ($currentItem !== null && $currentPath !== $dashboardPath) {
            $breadcrumbs[] = [
                'label' => $currentItem['label'],
                'href' => $currentItem['href'],
                'icon' => $currentItem['icon'] ?? null,
            ];
        }

        return array_merge(parent::share($request), [
            'app' => [
                'name' => config('app.name', 'VPN Admin'),
                'isAuthorized' => (bool) $request->attributes->get('isAuthorized'),
                'currentPath' => $request->path(),
                'navigation' => $navigation,
                'currentSection' => $currentSection,
                'currentItem' => $currentItem,
                'breadcrumbs' => $breadcrumbs,
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
