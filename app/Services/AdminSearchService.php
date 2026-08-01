<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Models\Server;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VlessConfig;

class AdminSearchService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        if ($query === '') {
            return [];
        }

        return array_values(array_filter([
            $this->users($query),
            $this->servers($query),
            $this->wireGuardConfigs($query),
            $this->xrayConfigs($query),
            $this->transactions($query),
        ], static fn (array $section): bool => $section['items'] !== []));
    }

    /**
     * @return array<string, mixed>
     */
    private function users(string $query): array
    {
        $items = User::query()
            ->where(function ($builder) use ($query): void {
                if (ctype_digit($query)) {
                    $builder->where('id', (int) $query);
                }

                $builder
                    ->orWhere('name', 'like', '%' . $query . '%')
                    ->orWhere('telegram', 'like', '%' . $query . '%');
            })
            ->orderByDesc('is_active')
            ->limit(8)
            ->get()
            ->map(fn (User $user): array => [
                'title' => $user->full_name,
                'meta' => $user->telegram ?: 'Telegram не указан',
                'description' => sprintf('ID %d · %s', $user->id, $user->is_active ? 'активен' : 'неактивен'),
                'href' => route('users.edit', $user),
            ])
            ->all();

        return [
            'label' => 'Пользователи',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function servers(string $query): array
    {
        $items = Server::query()
            ->where(function ($builder) use ($query): void {
                if (ctype_digit($query)) {
                    $builder->where('id', (int) $query);
                }

                $builder
                    ->orWhere('name', 'like', '%' . $query . '%')
                    ->orWhere('code', 'like', '%' . $query . '%')
                    ->orWhere('ip', 'like', '%' . $query . '%')
                    ->orWhere('link_host', 'like', '%' . $query . '%');
            })
            ->ordered()
            ->limit(8)
            ->get()
            ->map(fn (Server $server): array => [
                'title' => sprintf('%s (%s)', $server->name, $server->code),
                'meta' => $server->ip,
                'description' => sprintf('%s · %s', $server->type, $server->is_active ? 'активен' : 'отключен'),
                'href' => route('servers.edit', $server),
            ])
            ->all();

        return [
            'label' => 'Серверы',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wireGuardConfigs(string $query): array
    {
        $items = Config::query()
            ->with(['server:id,name,code', 'user:id,name,telegram'])
            ->where(function ($builder) use ($query): void {
                if (ctype_digit($query)) {
                    $builder->where('id', (int) $query);
                }

                $builder
                    ->orWhere('name', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
            })
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Config $config): array => [
                'title' => $config->name,
                'meta' => $config->user?->telegram ?: ($config->user?->name ?: 'Без пользователя'),
                'description' => sprintf('WireGuard · %s', $config->server?->name ?: 'Без сервера'),
                'href' => route('configs.edit', $config),
            ])
            ->all();

        return [
            'label' => 'WireGuard конфиги',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function xrayConfigs(string $query): array
    {
        $items = VlessConfig::query()
            ->with(['server:id,name,code', 'user:id,name,telegram'])
            ->where(function ($builder) use ($query): void {
                if (ctype_digit($query)) {
                    $builder->where('id', (int) $query);
                }

                $builder
                    ->orWhere('name', 'like', '%' . $query . '%')
                    ->orWhere('uuid', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
            })
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (VlessConfig $config): array {
                $protocol = mb_strtoupper((string) ($config->protocol ?: 'vless'));
                $routeProtocol = $config->protocol === 'vless' ? 'vless' : 'xray';

                return [
                    'title' => $config->name,
                    'meta' => $config->uuid ?: 'UUID не задан',
                    'description' => sprintf('%s · %s', $protocol, $config->server?->name ?: 'Без сервера'),
                    'href' => route('xray-configs.edit', [
                        'protocol' => $routeProtocol,
                        'config' => $config,
                    ]),
                ];
            })
            ->all();

        return [
            'label' => 'Xray / VLESS конфиги',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactions(string $query): array
    {
        $items = Transaction::query()
            ->with(['user:id,name,telegram', 'type:id,name'])
            ->where(function ($builder) use ($query): void {
                if (is_numeric($query)) {
                    $builder
                        ->where('id', (int) $query)
                        ->orWhere('amount', (float) $query);
                }

                $builder
                    ->orWhere('description', 'like', '%' . $query . '%')
                    ->orWhereHas('user', function ($userQuery) use ($query): void {
                        $userQuery
                            ->where('name', 'like', '%' . $query . '%')
                            ->orWhere('telegram', 'like', '%' . $query . '%');
                    });
            })
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'title' => sprintf('Транзакция #%d', $transaction->id),
                'meta' => sprintf('%s ₽', number_format((float) $transaction->amount, 2, '.', ' ')),
                'description' => sprintf(
                    '%s · %s',
                    $transaction->user?->telegram ?: ($transaction->user?->name ?: 'Без пользователя'),
                    $transaction->type?->name ?: 'Без типа',
                ),
                'href' => route('transactions.edit', $transaction),
            ])
            ->all();

        return [
            'label' => 'Транзакции',
            'items' => $items,
        ];
    }
}
