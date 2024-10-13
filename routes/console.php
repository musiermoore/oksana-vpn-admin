<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('wireguard:calculate-peers-traffic', function () {
    $this->comment(Inspiring::quote());
})->everyFiveMinutes();
