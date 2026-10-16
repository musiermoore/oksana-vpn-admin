<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InboundHealthCheckScheduler;
use Illuminate\Console\Command;

final class CheckXrayInboundHealthCommand extends Command
{
    protected $signature = 'xray-inbounds:check-health';

    protected $description = 'Queue direct inbound and subscription-profile health checks';

    public function handle(InboundHealthCheckScheduler $scheduler): int
    {
        $inboundCount = $scheduler->dispatch();

        $this->info(sprintf('Queued direct checks for %d active inbounds, followed by the technical-user subscription check.', $inboundCount));

        return self::SUCCESS;
    }
}
