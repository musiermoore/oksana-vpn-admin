<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckXrayInboundHealthJob;
use App\Models\XrayInbound;
use Illuminate\Console\Command;

final class CheckXrayInboundHealthCommand extends Command
{
    protected $signature = 'xray-inbounds:check-health';

    protected $description = 'Queue end-to-end health checks for active Xray inbounds';

    public function handle(): int
    {
        $inboundIds = XrayInbound::query()
            ->where('is_active', true)
            ->whereHas('server', fn ($query) => $query->where('is_active', true)->where('is_ready', true))
            ->pluck('id');

        foreach ($inboundIds as $inboundId) {
            CheckXrayInboundHealthJob::dispatch((int) $inboundId)->afterCommit();
        }

        $this->info(sprintf('Queued health checks for %d active inbounds.', $inboundIds->count()));

        return self::SUCCESS;
    }
}
