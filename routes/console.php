<?php

use App\Console\Commands\AddExtraPayments;
use App\Console\Commands\CalculatePeersTraffic;
use App\Console\Commands\CreateDefaultConfigsForActiveSubscribersCommand;
use App\Console\Commands\DisableConfigsOfOverdueDebtorsCommand;
use App\Console\Commands\PullVlessConfigs;
use App\Console\Commands\RemoveOldTrafficLogs;
use App\Console\Commands\RenewSubscriptionsCommand;
use App\Console\Commands\SendPaidInvoicesToTaxCommand;
use App\Console\Commands\SyncVlessExternalSubscriptionsCommand;
use App\Jobs\ReleaseBlockedConfigsJob;
use App\Jobs\SyncServerOnlineClientsJob;
use App\Jobs\SyncServerUserStatsJob;
use App\Models\Server;

Schedule::command(CalculatePeersTraffic::class)->everyMinute();
Schedule::command(RemoveOldTrafficLogs::class)->everyMinute();
Schedule::command(PullVlessConfigs::class)->everyMinute();
Schedule::command(SyncVlessExternalSubscriptionsCommand::class)->everyFifteenMinutes();
Schedule::command(AddExtraPayments::class)->hourly();
Schedule::command(CreateDefaultConfigsForActiveSubscribersCommand::class)->everyFiveMinutes();
Schedule::command(RenewSubscriptionsCommand::class)->everyFiveMinutes();
Schedule::command(DisableConfigsOfOverdueDebtorsCommand::class)->everyThirtyMinutes();
Schedule::command(SendPaidInvoicesToTaxCommand::class)->dailyAt('06:00')->timezone('UTC');

Schedule::call(function (): void {
    Server::query()
        ->vless()
        ->where('is_ready', true)
        ->whereNotNull(['panel_link', 'panel_username', 'panel_password'])
        ->pluck('id')
        ->each(fn (int $serverId) => SyncServerOnlineClientsJob::dispatch($serverId));
})->everyMinute();

Schedule::call(function (): void {
    Server::query()
        ->vless()
        ->where('is_ready', true)
        ->whereNotNull(['panel_link', 'panel_username', 'panel_password'])
        ->pluck('id')
        ->each(fn (int $serverId) => SyncServerUserStatsJob::dispatch($serverId));
})->everyFiveMinutes();

Schedule::job(new ReleaseBlockedConfigsJob)->everyMinute();
