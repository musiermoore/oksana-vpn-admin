<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use App\Services\Crud\VlessConfigCrudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DisableConfigsOfOverdueDebtorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'configs:disable-overdue-debtors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable configs of overdue debtors';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->disableWireGuardConfigs();
        $this->enableWireGuardConfigs();
        $this->disableVlessConfigs();
        $this->enableVlessConfigs();
    }

    private function getQuery()
    {
        return User::query()
            ->with(['activeSubscription'])
            ->select([
                'users.id',
                'users.telegram',
                DB::raw('COALESCE(users.balance, 0) AS final_balance'),
            ])
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
            ->filter(fn (User $user) => $user->final_balance < 0 || ! $user->hasActiveSubscription());

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
            ->filter(fn (User $user) => $user->final_balance >= 0 && $user->hasActiveSubscription());

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
            ->filter(fn (User $user) => $user->final_balance < 0 || ! $user->hasActiveSubscription());

        $ids = [];
        $service = app(VlessConfigCrudService::class);

        foreach ($users as $user) {
            foreach ($user->vlessConfigs as $config) {
                try {
                    $service->disable($config);
                    $ids[] = $config->id;
                } catch (RuntimeException $exception) {
                    report($exception);
                    $this->warn("Failed to disable VLESS config [{$config->id}] for user [{$user->id}]");
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
            ->filter(fn (User $user) => $user->final_balance >= 0 && $user->hasActiveSubscription());

        $ids = [];
        $service = app(VlessConfigCrudService::class);

        foreach ($users as $user) {
            foreach ($user->vlessConfigs as $config) {
                try {
                    $service->enable($config);
                    $ids[] = $config->id;
                } catch (RuntimeException $exception) {
                    report($exception);
                    $this->warn("Failed to enable VLESS config [{$config->id}] for user [{$user->id}]");
                }
            }
        }

        VlessConfig::whereIn('id', $ids)->update(['enable' => true]);
    }
}
