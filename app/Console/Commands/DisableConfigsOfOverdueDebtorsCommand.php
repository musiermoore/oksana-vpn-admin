<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        User::syncAllStoredBalances();
        app(SubscriptionService::class)->renewEligibleSubscriptions();
        User::syncAllStoredBalances();
        $this->disableConfigs();
        $this->enableConfigs();
    }

    private function getQuery()
    {
        return User::query()
            ->with(['configs', 'activeSubscription'])
            ->select([
                'users.id',
                'users.telegram',
                DB::raw('COALESCE(users.balance, 0) AS final_balance'),
            ])
            ->groupBy('users.id');
    }

    private function disableConfigs(): void
    {
        $users = $this->getQuery()
            ->whereHas('configs', function ($query) {
                $query->where('is_active', '=', true);
            })
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

    private function enableConfigs(): void
    {
        $users = $this->getQuery()
            ->whereHas('configs', function ($query) {
                $query->where('is_active', '=', false);
            })
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
}
