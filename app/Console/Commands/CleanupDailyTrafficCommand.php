<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UserDailyTraffic;
use Illuminate\Console\Command;

class CleanupDailyTrafficCommand extends Command
{
    private const RETENTION_DAYS = 90;

    protected $signature = 'traffic:cleanup-daily';

    protected $description = 'Delete daily traffic totals older than the retention period';

    public function handle(): int
    {
        $cutoff = today()->subDays(self::RETENTION_DAYS - 1)->toDateString();
        $deletedRows = UserDailyTraffic::query()
            ->where('traffic_date', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deletedRows} daily traffic rows older than {$cutoff}.");

        return self::SUCCESS;
    }
}
