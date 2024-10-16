<?php

namespace App\Console\Commands;

use App\Models\HighTrafficLog;
use App\Models\Traffic;
use Illuminate\Console\Command;

class RemoveOldTrafficLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wireguard:prune-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $days = now()->subDays(2);

        HighTrafficLog::query()
            ->where('created_at', '<', $days)
            ->delete();

        Traffic::query()
            ->where('created_at', '<', $days)
            ->delete();
    }
}
