<?php

namespace App\Console\Commands;

use App\Models\Traffic;
use App\Services\WireGuardService;
use App\Services\WireGuardTrafficService;
use Illuminate\Console\Command;

class CalculatePeersTraffic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wireguard:calculate-peers-traffic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate peers traffic and store it to DB';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $traffics = WireGuardTrafficService::getTraffic();

        Traffic::insert($traffics);
    }
}
