<?php

use App\Console\Commands\CalculatePeersTraffic;
use App\Console\Commands\DetectHighTraffic;

Schedule::command(CalculatePeersTraffic::class)->everyMinute();
Schedule::command(DetectHighTraffic::class)->everyMinute();
