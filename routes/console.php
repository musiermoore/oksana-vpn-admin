<?php

use App\Console\Commands\CalculatePeersTraffic;

Schedule::command(CalculatePeersTraffic::class)->everyFiveMinutes();
