<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\CurrentPayment;
use App\Models\Transaction;
use App\Models\User;
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
        $this->disableConfigs();
        $this->enableConfigs();
    }

    private function getQuery()
    {
        $transactionsJoin = Transaction::query()
            ->select(['user_id', DB::raw('SUM(IFNULL(amount, 0)) AS balance')])
            ->groupBy('user_id');

        return User::query()
            ->with('configs')
            ->select([
                'users.id',
                'users.telegram',
                DB::raw('IFNULL(balance, 0) - SUM(amount) + users.extra_payment AS final_balance'),
            ])
            ->leftJoinSub($transactionsJoin, 'transactions', 'transactions.user_id', '=', 'users.id')
            ->leftJoin('current_payments', function ($join) {
                $join
                    ->where(function ($query) {
                        $query
                            ->where('start_date', '>=', DB::raw('users.join_at'))
                            ->orWhereNull('join_at');
                    })
                    ->where('start_date', '<=', DB::raw('CURRENT_TIMESTAMP()'));
            })
            ->groupBy('users.id');
    }

    private function disableConfigs(): void
    {
        $lastPeriod = CurrentPayment::orderByDesc('start_date')->value('amount');

        $users = $this->getQuery()
            ->whereHas('configs', function ($query) {
                $query->where('is_active', '=', true);
            })
            ->having('final_balance', '<', -$lastPeriod)
            ->get();

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
            ->having('final_balance', '>=', 0)
            ->where('is_active', '=', true)
            ->get();

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
