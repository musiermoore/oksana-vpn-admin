<?php

use App\Console\Commands\AddExtraPayments;
use App\Console\Commands\CalculatePeersTraffic;
use App\Console\Commands\CreateDefaultConfigsForActiveSubscribersCommand;
use App\Console\Commands\DisableConfigsOfOverdueDebtorsCommand;
use App\Console\Commands\PullVlessConfigs;
use App\Console\Commands\RenewSubscriptionsCommand;
use App\Console\Commands\RemoveOldTrafficLogs;

Schedule::command(CalculatePeersTraffic::class)->everyMinute();
Schedule::command(RemoveOldTrafficLogs::class)->everyMinute();
Schedule::command(PullVlessConfigs::class)->everyMinute();
Schedule::command(AddExtraPayments::class)->hourly();
Schedule::command(CreateDefaultConfigsForActiveSubscribersCommand::class)->everyFiveMinutes();
Schedule::command(RenewSubscriptionsCommand::class)->everyFiveMinutes();
Schedule::command(DisableConfigsOfOverdueDebtorsCommand::class)->everyThirtyMinutes();
