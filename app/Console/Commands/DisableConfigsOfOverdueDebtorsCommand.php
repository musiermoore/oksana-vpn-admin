<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use App\Services\Crud\VlessConfigCrudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DisableConfigsOfOverdueDebtorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'configs:disable-overdue-debtors {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile config access by active subscription state';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->syncStoredBalances();
        $this->disableWireGuardConfigs();
        $this->enableWireGuardConfigs();
        $this->disableVlessConfigs();
        $this->enableVlessConfigs();

        return self::SUCCESS;
    }

    private function syncStoredBalances(): void
    {
        $userId = $this->argument('user_id');

        $balances = DB::table('transactions')
            ->selectRaw('user_id, COALESCE(SUM(amount), 0) AS balance')
            ->where('is_approved', true)
            ->when($userId, fn ($query, $value) => $query->where('user_id', $value))
            ->groupBy('user_id')
            ->pluck('balance', 'user_id');

        User::query()
            ->select('id')
            ->when($userId, fn ($query, $value) => $query->whereKey($value))
            ->chunkById(200, function ($users) use ($balances): void {
                foreach ($users as $user) {
                    User::query()
                        ->whereKey($user->id)
                        ->update([
                            'balance' => round((float) ($balances[$user->id] ?? 0.0), 2),
                        ]);
                }
            });
    }

    private function getQuery()
    {
        return User::query()
            ->with(['activeSubscription'])
            ->select([
                'users.id',
                'users.telegram',
            ])
            ->when($this->argument('user_id'), fn ($query, $userId) => $query->whereKey($userId))
            ->groupBy('users.id');
    }

    private function disableWireGuardConfigs(): void
    {
        $users = $this->getQuery()
            ->whereHas('configs', function ($query) {
                $query
                    ->where('is_active', '=', true)
                    ->whereHas('server', fn ($serverQuery) => $serverQuery->whereIn('type', Server::wireGuardTypes()));
            })
            ->with(['configs' => function ($query) {
                $query
                    ->where('is_active', '=', true)
                    ->whereHas('server', fn ($serverQuery) => $serverQuery->whereIn('type', Server::wireGuardTypes()));
            }])
            ->get()
            ->filter(fn (User $user) => ! $user->hasActiveSubscription());

        $ids = [];

        foreach ($users as $user) {
            foreach ($user->configs as $config) {
                $ids[] = $config->id;
                $config->disableWgConfig();
            }
        }

        Config::whereIn('id', $ids)->update(['is_active' => false]);
    }

    private function enableWireGuardConfigs(): void
    {
        $users = $this->getQuery()
            ->whereHas('configs', function ($query) {
                $query
                    ->where('is_active', '=', false)
                    ->whereHas('server', fn ($serverQuery) => $serverQuery->whereIn('type', Server::wireGuardTypes()));
            })
            ->with(['configs' => function ($query) {
                $query
                    ->where('is_active', '=', false)
                    ->whereHas('server', fn ($serverQuery) => $serverQuery->whereIn('type', Server::wireGuardTypes()));
            }])
            ->where('is_active', '=', true)
            ->get()
            ->filter(fn (User $user) => $user->hasActiveSubscription());

        $ids = [];

        foreach ($users as $user) {
            foreach ($user->configs as $config) {
                $ids[] = $config->id;
                $config->enableWgConfig();
            }
        }

        Config::whereIn('id', $ids)->update(['is_active' => true]);
    }

    private function disableVlessConfigs(): void
    {
        $users = $this->getQuery()
            ->whereHas('vlessConfigs', function ($query) {
                $query
                    ->where('enable', '=', true)
                    ->whereHas('server', fn ($serverQuery) => $serverQuery->where('type', Server::TYPE_VLESS));
            })
            ->with(['vlessConfigs' => function ($query) {
                $query
                    ->where('enable', '=', true)
                    ->whereHas('server', fn ($serverQuery) => $serverQuery->where('type', Server::TYPE_VLESS));
            }])
            ->get()
            ->filter(fn (User $user) => ! $user->hasActiveSubscription());

        $ids = [];
        $service = app(VlessConfigCrudService::class);

        foreach ($users as $user) {
            foreach ($user->vlessConfigs as $config) {
                if ($config->getResolvedInboundId() === null) {
                    $ids[] = $config->id;
                    $this->warn("Skipping remote disable for VLESS config [{$config->id}] because inbound is missing; config will be disabled locally.");

                    continue;
                }

                try {
                    $service->disable($config);
                    $ids[] = $config->id;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->warn("Failed to disable VLESS config [{$config->id}] for user [{$user->id}]: {$exception->getMessage()}");
                }
            }
        }

        VlessConfig::whereIn('id', $ids)->update(['enable' => false]);
    }

    private function enableVlessConfigs(): void
    {
        $users = $this->getQuery()
            ->whereHas('vlessConfigs', function ($query) {
                $query
                    ->where('enable', '=', false)
                    ->whereHas('server', fn ($serverQuery) => $serverQuery->where('type', Server::TYPE_VLESS));
            })
            ->with(['vlessConfigs' => function ($query) {
                $query
                    ->where('enable', '=', false)
                    ->whereHas('server', fn ($serverQuery) => $serverQuery->where('type', Server::TYPE_VLESS));
            }])
            ->where('is_active', '=', true)
            ->get()
            ->filter(fn (User $user) => $user->hasActiveSubscription());

        $ids = [];
        $service = app(VlessConfigCrudService::class);

        foreach ($users as $user) {
            foreach ($user->vlessConfigs as $config) {
                try {
                    $service->enable($config);
                    $ids[] = $config->id;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->warn("Failed to enable VLESS config [{$config->id}] for user [{$user->id}]: {$exception->getMessage()}");
                }
            }
        }

        VlessConfig::whereIn('id', $ids)->update(['enable' => true]);
    }
}
