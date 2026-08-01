<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SupportTicketStatus;
use App\Models\ApiRequestLog;
use App\Models\Invoice;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VlessExternalSubscription;
use Carbon\CarbonInterface;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $activeServers = Server::query()->where('is_active', true)->count();
        $serverWarnings = Server::query()
            ->where(fn ($query) => $query->where('is_active', false)->orWhere('is_ready', false))
            ->count();
        $pendingTransactions = Transaction::query()->where('is_approved', false)->count();
        $openSupportTickets = SupportTicket::query()
            ->where('status', SupportTicketStatus::Open->value)
            ->count();
        $integrationErrors = ApiRequestLog::query()
            ->where('created_at', '>=', now()->subDay())
            ->where('response_status', '>=', 400)
            ->count();
        $tasksInProgress = Task::query()->where('status', Task::STATUS_IN_PROGRESS)->count();

        $attentionItems = collect([
            ...$this->pendingTransactionAttentionItems(),
            ...$this->serverAttentionItems(),
            ...$this->invoiceAttentionItems(),
            ...$this->supportAttentionItems(),
        ])
            ->sortByDesc('sort_key')
            ->take(12)
            ->map(function (array $item): array {
                unset($item['sort_key']);

                return $item;
            })
            ->values()
            ->all();

        return [
            'status_strip' => [
                [
                    'label' => 'Сеть',
                    'value' => $serverWarnings > 0 ? 'Есть предупреждения' : 'Работает',
                    'meta' => sprintf('%d активных серверов', $activeServers),
                    'tone' => $serverWarnings > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Биллинг',
                    'value' => $pendingTransactions > 0 ? 'Ожидаются решения' : 'Работает',
                    'meta' => sprintf('%d транзакций на рассмотрении', $pendingTransactions),
                    'tone' => $pendingTransactions > 0 ? 'warning' : 'success',
                ],
                [
                    'label' => 'Поддержка',
                    'value' => (string) $openSupportTickets,
                    'meta' => 'Открытых тикетов',
                    'tone' => $openSupportTickets > 0 ? 'warning' : 'muted',
                ],
                [
                    'label' => 'Интеграции',
                    'value' => (string) $integrationErrors,
                    'meta' => 'Ошибок API за 24 часа',
                    'tone' => $integrationErrors > 0 ? 'danger' : 'success',
                ],
                [
                    'label' => 'Фоновые задачи',
                    'value' => (string) $tasksInProgress,
                    'meta' => 'Задач в работе',
                    'tone' => $tasksInProgress > 0 ? 'muted' : 'success',
                ],
            ],
            'quick_actions' => [
                [
                    'label' => 'Добавить сервер',
                    'href' => route('servers.create'),
                    'variant' => 'primary',
                ],
                [
                    'label' => 'Найти пользователя',
                    'href' => route('users.index', ['telegram' => '']),
                    'variant' => 'secondary',
                ],
                [
                    'label' => 'Создать транзакцию',
                    'href' => route('transactions.create'),
                    'variant' => 'secondary',
                ],
                [
                    'label' => 'Открыть диагностику',
                    'href' => route('api-request-logs.index'),
                    'variant' => 'ghost',
                ],
            ],
            'create_actions' => [
                ['label' => 'Сервер', 'href' => route('servers.create')],
                ['label' => 'Прокси', 'href' => route('proxies.create')],
                ['label' => 'Пользователь', 'href' => route('users.create')],
                ['label' => 'Транзакция', 'href' => route('transactions.create')],
                ['label' => 'Инвойс', 'href' => route('invoices.index')],
                ['label' => 'Задача', 'href' => route('tasks.create')],
            ],
            'sections' => [
                [
                    'label' => 'Сеть',
                    'icon' => 'server',
                    'description' => 'Серверы, Xray-конфигурации, прокси и whitelist-подписки.',
                    'links' => [
                        ['label' => 'Серверы', 'href' => route('servers.index')],
                        ['label' => 'Xray Configs', 'href' => route('xray-configs.index')],
                        ['label' => 'Прокси', 'href' => route('proxies.index')],
                        ['label' => 'VLESS WL', 'href' => route('vless-external-subscriptions.index')],
                    ],
                    'highlights' => [
                        sprintf('%d активных серверов', $activeServers),
                        sprintf('%d серверов с предупреждениями', $serverWarnings),
                        sprintf('%d активных whitelist-подписок', VlessExternalSubscription::query()->where('is_active', true)->count()),
                    ],
                ],
                [
                    'label' => 'Пользователи',
                    'icon' => 'users',
                    'description' => 'Участники сервиса, реферальные связи и обращения в поддержку.',
                    'links' => [
                        ['label' => 'Участники', 'href' => route('users.index')],
                        ['label' => 'Рефералка', 'href' => route('referrals.index')],
                        ['label' => 'Поддержка', 'href' => route('support-tickets.index')],
                    ],
                    'highlights' => [
                        sprintf('%d активных пользователей', User::query()->where('is_active', true)->count()),
                        sprintf('%d открытых тикетов', $openSupportTickets),
                    ],
                ],
                [
                    'label' => 'Биллинг',
                    'icon' => 'wallet',
                    'description' => 'Транзакции, подписки, инвойсы и налоговые операции.',
                    'links' => [
                        ['label' => 'Транзакции', 'href' => route('transactions.index')],
                        ['label' => 'Инвойсы', 'href' => route('invoices.index')],
                        ['label' => 'Подписки', 'href' => route('subscriptions.index')],
                        ['label' => 'Периоды оплаты', 'href' => route('current-payments.index')],
                        ['label' => 'Налоги', 'href' => route('tax-settings.edit')],
                    ],
                    'highlights' => [
                        sprintf('%d транзакций на рассмотрении', $pendingTransactions),
                        sprintf('%d tax-ошибок по инвойсам', Invoice::query()->where('tax_status', Invoice::TAX_STATUS_FAILED)->count()),
                    ],
                ],
                [
                    'label' => 'Операции',
                    'icon' => 'tasks',
                    'description' => 'Задачи, массовые коммуникации и welcome-контент.',
                    'links' => [
                        ['label' => 'Задачи', 'href' => route('tasks.index')],
                        ['label' => 'Рассылка', 'href' => route('notifications.create')],
                        ['label' => 'Welcome', 'href' => route('messages.welcome.edit')],
                    ],
                    'highlights' => [
                        sprintf('%d задач в работе', $tasksInProgress),
                        sprintf('%d задач ждут старта', Task::query()->where('status', Task::STATUS_TODO)->count()),
                    ],
                ],
                [
                    'label' => 'Диагностика',
                    'icon' => 'activity',
                    'description' => 'Технические логи, запросы интеграций и инструменты отладки.',
                    'links' => [
                        ['label' => 'API лог', 'href' => route('api-request-logs.index')],
                        ['label' => '3x-ui Debug', 'href' => route('xui-debug.index')],
                        ['label' => 'Tax Debug', 'href' => route('tax-debug.index')],
                        ['label' => 'WireGuard Peers', 'href' => route('wireguard.active-peers')],
                    ],
                    'highlights' => [
                        sprintf('%d ошибок API за сутки', $integrationErrors),
                        sprintf('%d ошибок tax-отправки', Invoice::query()->where('tax_status', Invoice::TAX_STATUS_FAILED)->count()),
                    ],
                ],
            ],
            'attention_items' => $attentionItems,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function pendingTransactionAttentionItems(): array
    {
        return Transaction::query()
            ->with('user:id,name,telegram')
            ->where('is_approved', false)
            ->latest('created_at')
            ->limit(3)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'type' => 'Транзакция',
                'description' => sprintf(
                    '#%d · %s · %.2f ₽',
                    $transaction->id,
                    $transaction->user?->telegram ?: $transaction->user?->name ?: 'Без пользователя',
                    (float) $transaction->amount,
                ),
                'time' => $this->formatTime($transaction->created_at),
                'status' => 'На рассмотрении',
                'href' => route('transactions.index'),
                'sort_key' => $transaction->created_at?->timestamp ?? 0,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function serverAttentionItems(): array
    {
        return Server::query()
            ->where(fn ($query) => $query->where('is_active', false)->orWhere('is_ready', false))
            ->ordered()
            ->limit(3)
            ->get()
            ->map(fn (Server $server) => [
                'type' => 'Сервер',
                'description' => sprintf(
                    '%s · %s',
                    $server->name,
                    $server->is_active ? 'не готов к работе' : 'отключен',
                ),
                'time' => $this->formatTime($server->updated_at),
                'status' => $server->is_active ? 'Warning' : 'Disabled',
                'href' => route('servers.edit', $server),
                'sort_key' => $server->updated_at?->timestamp ?? 0,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function invoiceAttentionItems(): array
    {
        return Invoice::query()
            ->where('tax_status', Invoice::TAX_STATUS_FAILED)
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'type' => 'Инвойс',
                'description' => sprintf('#%d · %.2f ₽ · ошибка tax', $invoice->id, (float) $invoice->amount),
                'time' => $this->formatTime($invoice->updated_at),
                'status' => 'Ошибка',
                'href' => route('invoices.show', $invoice),
                'sort_key' => $invoice->updated_at?->timestamp ?? 0,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function supportAttentionItems(): array
    {
        return SupportTicket::query()
            ->with('user:id,name,telegram')
            ->where('status', SupportTicketStatus::Open->value)
            ->latest('last_message_at')
            ->limit(3)
            ->get()
            ->map(fn (SupportTicket $ticket) => [
                'type' => 'Тикет',
                'description' => sprintf(
                    '#%d · %s',
                    $ticket->id,
                    $ticket->subject ?: ($ticket->user?->telegram ?: $ticket->user?->name ?: 'Без темы'),
                ),
                'time' => $this->formatTime($ticket->last_message_at),
                'status' => 'Открыт',
                'href' => route('support-tickets.show', ['ticketId' => $ticket->id]),
                'sort_key' => $ticket->last_message_at?->timestamp ?? 0,
            ])
            ->all();
    }

    private function formatTime(CarbonInterface|string|null $value): string
    {
        if (! $value instanceof CarbonInterface) {
            return 'Недавно';
        }

        return $value->diffForHumans(short: true);
    }
}
