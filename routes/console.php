<?php

use App\Console\Commands\CalculatePeersTraffic;
use App\Console\Commands\DetectHighTraffic;
use App\Console\Commands\DisableConfigsOfOverdueDebtorsCommand;
use App\Console\Commands\RemoveOldTrafficLogs;

Schedule::command(CalculatePeersTraffic::class)->everyMinute();
Schedule::command(DetectHighTraffic::class)->everyMinute();
Schedule::command(RemoveOldTrafficLogs::class)->everyMinute();
Schedule::command(DisableConfigsOfOverdueDebtorsCommand::class)->everyThirtyMinutes();
