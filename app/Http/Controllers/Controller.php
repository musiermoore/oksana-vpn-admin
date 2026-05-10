<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\CurrentPayment;
use App\Models\Limit;
use App\Models\Server;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserExtraPayment;
use App\Models\UserSubscription;
use App\Models\UserToken;
use App\Models\VlessConfig;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Inertia\Inertia;
use Inertia\Response;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function inertia(string $component, array $props = []): Response
    {
        return Inertia::render($component, $props);
    }

    protected function userData(User $user, bool $includeRelations = false): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'telegram' => $user->telegram,
            'telegram_id' => $user->telegram_id,
            'description' => $user->description,
            'join_at' => $user->join_at,
            'balance' => (float) ($user->balance ?? 0),
            'is_active' => $user->is_active,
            'full_name' => $user->full_name,
            'approved_transactions_sum_amount' => (float) ($user->approved_transactions_sum_amount ?? 0),
            'payment_amount' => (float) ($user->payment_amount ?? 0),
            'links' => [
                'edit' => route('users.edit', $user),
                'destroy' => route('users.destroy', $user),
            ],
        ];

        if ($includeRelations) {
            $data['configs'] = $user->configs
                ->map(fn (Config $config) => $this->configData($config))
                ->values()
                ->all();
            $data['transactions'] = $user->transactions
                ->map(fn (Transaction $transaction) => $this->transactionData($transaction))
                ->values()
                ->all();
        }

        return $data;
    }

    protected function configData(Config $config): array
    {
        return [
            'id' => $config->id,
            'name' => $config->name,
            'description' => $config->description,
            'address' => $config->address,
            'is_active' => (bool) $config->is_active,
            'server' => $config->server ? $this->serverData($config->server) : null,
            'user' => $config->user ? [
                'id' => $config->user->id,
                'full_name' => $config->user->full_name,
                'is_active' => $config->user->is_active,
            ] : null,
            'formatted_last_traffic' => $config->formatted_last_traffic,
            'links' => [
                'edit' => route('configs.edit', $config),
                'destroy' => route('configs.destroy', $config),
                'enable' => route('configs.enable', $config),
                'disable' => route('configs.disable', $config),
            ],
        ];
    }

    protected function vlessConfigData(VlessConfig $config): array
    {
        return [
            'id' => $config->id,
            'name' => $config->name,
            'is_active' => (bool) $config->is_active,
            'enable' => (bool) $config->enable,
            'link' => $config->link,
            'server' => $config->server ? $this->serverData($config->server) : null,
            'user' => $config->user ? [
                'id' => $config->user->id,
                'full_name' => $config->user->full_name,
                'is_active' => $config->user->is_active,
            ] : null,
            'links' => [
                'edit' => route('vless-configs.edit', $config),
                'destroy' => route('vless-configs.destroy', $config),
                'enable' => route('vless-configs.enable', $config),
                'disable' => route('vless-configs.disable', $config),
            ],
        ];
    }

    protected function serverData(Server $server, bool $includeCredentials = false): array
    {
        $data = [
            'id' => $server->id,
            'name' => $server->name,
            'code' => $server->code,
            'ip' => $server->ip,
            'is_https' => (bool) $server->is_https,
            'link_host' => $server->link_host,
            'panel_link' => $server->panel_link,
            'panel_username' => $server->panel_username,
            'app_path' => $server->app_path,
            'ssh_public_key' => $server->ssh_public_key,
            'is_vless' => (bool) $server->is_vless,
            'links' => [
                'edit' => route('servers.edit', $server),
                'destroy' => route('servers.destroy', $server),
            ],
        ];

        if ($includeCredentials) {
            $data['panel_password'] = $server->panel_password;
        }

        return $data;
    }

    protected function transactionData(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'amount' => (float) $transaction->amount,
            'is_approved' => (bool) $transaction->is_approved,
            'description' => $transaction->description,
            'formatted_created_at' => $transaction->formatted_created_at,
            'type' => $transaction->type ? $this->transactionTypeData($transaction->type) : null,
            'user' => $transaction->user ? [
                'id' => $transaction->user->id,
                'full_name' => $transaction->user->full_name,
                'is_active' => $transaction->user->is_active,
                'edit_url' => route('users.edit', $transaction->user),
            ] : null,
            'links' => [
                'edit' => route('transactions.edit', $transaction),
                'destroy' => route('transactions.destroy', $transaction),
                'approve' => route('transactions.approve', $transaction),
                'decline' => route('transactions.decline', $transaction),
            ],
        ];
    }

    protected function transactionTypeData(TransactionType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'slug' => $type->slug,
        ];
    }

    protected function currentPaymentData(CurrentPayment $currentPayment): array
    {
        return [
            'id' => $currentPayment->id,
            'start_date' => $currentPayment->start_date,
            'end_date' => $currentPayment->end_date,
            'amount' => (float) $currentPayment->amount,
            'formatted_start_date' => $currentPayment->formatted_start_date,
            'formatted_end_date' => $currentPayment->formatted_end_date,
            'full_date' => $currentPayment->full_date,
            'links' => [
                'edit' => route('current-payments.edit', $currentPayment),
                'destroy' => route('current-payments.destroy', $currentPayment),
            ],
        ];
    }

    protected function extraPaymentData(UserExtraPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'user' => $payment->user ? [
                'id' => $payment->user->id,
                'full_name' => $payment->user->full_name,
                'is_active' => $payment->user->is_active,
                'edit_url' => route('users.edit', $payment->user),
            ] : null,
            'current_payment' => $payment->currentPayment ? [
                'id' => $payment->currentPayment->id,
                'full_date' => $payment->currentPayment->full_date,
            ] : null,
            'links' => [
                'destroy' => route('extra-payments.destroy', $payment),
            ],
        ];
    }

    protected function limitData(Limit $limit): array
    {
        return [
            'id' => $limit->id,
            'amount' => (int) $limit->amount,
            'links' => [
                'destroy' => route('limits.destroy', $limit),
            ],
        ];
    }

    protected function userTokenData(UserToken $userToken): array
    {
        return [
            'id' => $userToken->id,
            'token' => $userToken->token,
            'password' => $userToken->password,
            'expires_at' => $userToken->expires_at,
            'user' => $userToken->user ? [
                'id' => $userToken->user->id,
                'name' => $userToken->user->name,
                'telegram' => $userToken->user->telegram,
                'full_name' => $userToken->user->full_name,
            ] : null,
            'links' => [
                'show' => route('user-tokens.show', $userToken),
                'destroy' => route('user-tokens.destroy', $userToken),
                'public_configs' => route('users.configs', $userToken->token),
            ],
        ];
    }

    protected function userSubscriptionData(UserSubscription $subscription): array
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
