<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckXrayInboundHealthJob;
use App\Jobs\CheckSubscriptionProfilesHealthJob;
use App\Models\XrayInbound;
use Illuminate\Console\Command;

final class CheckXrayInboundHealthCommand extends Command
{
    protected $signature = 'xray-inbounds:check-health';

    protected $description = 'Queue direct inbound and subscription-profile health checks';

    public function handle(): int
    {
        $inboundIds = XrayInbound::query()
            ->where('is_active', true)
            ->whereHas('server', fn ($query) => $query->where('is_active', true)->where('is_ready', true))
            ->pluck('id');

        foreach ($inboundIds as $inboundId) {
            CheckXrayInboundHealthJob::dispatch((int) $inboundId)->afterCommit();
        }

        CheckSubscriptionProfilesHealthJob::dispatch()->afterCommit();

        $this->info(sprintf('Queued direct checks for %d active inbounds and the technical-user subscription check.', $inboundIds->count()));

        return self::SUCCESS;
    }
}
