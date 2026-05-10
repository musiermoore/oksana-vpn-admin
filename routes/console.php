<?php

use App\Console\Commands\AddExtraPayments;
use App\Console\Commands\CalculatePeersTraffic;
use App\Console\Commands\CreateDefaultConfigsForActiveSubscribersCommand;
use App\Console\Commands\DetectHighTraffic;
use App\Console\Commands\DisableConfigsOfOverdueDebtorsCommand;
use App\Console\Commands\PullVlessConfigs;
use App\Console\Commands\RenewSubscriptionsCommand;
use App\Console\Commands\RemoveOldTrafficLogs;

Schedule::command(CalculatePeersTraffic::class)->everyMinute();
Schedule::command(DetectHighTraffic::class)->everyMinute();
Schedule::command(RemoveOldTrafficLogs::class)->everyMinute();
Schedule::command(PullVlessConfigs::class)->everyMinute();
Schedule::command(AddExtraPayments::class)->hourly();
Schedule::command(CreateDefaultConfigsForActiveSubscribersCommand::class)->everyThirtyMinutes();
Schedule::command(RenewSubscriptionsCommand::class)->everyThirtyMinutes();
Schedule::command(DisableConfigsOfOverdueDebtorsCommand::class)->everyThirtyMinutes();
