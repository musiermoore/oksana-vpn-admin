<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\InboundHealthCheckSettings;
use App\Services\SubscriptionProfileHealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CheckSubscriptionProfilesHealthJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 80;

    public int $uniqueFor = 900;

    public function __construct()
    {
        $this->onQueue('slow');
    }

    public function uniqueId(): string
    {
        return 'subscription-profile-health';
    }

    public function handle(SubscriptionProfileHealthService $healthService, InboundHealthCheckSettings $settings): void
    {
        if (! $settings->enabled()) {
            return;
        }

        $healthService->check();
    }
}
