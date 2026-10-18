<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\CheckSubscriptionProfilesHealthJob;
use App\Jobs\CheckXrayInboundHealthJob;
use App\Models\XrayInbound;
use Throwable;
use Illuminate\Support\Facades\Bus;

final class InboundHealthCheckScheduler
{
    public function __construct(private readonly InboundHealthCheckSettings $settings) {}

    public function dispatch(): int
    {
        if (! $this->settings->enabled()) {
            return 0;
        }

        $jobs = XrayInbound::query()
            ->where('is_active', true)
            ->whereHas('server', fn ($query) => $query->where('is_active', true)->where('is_ready', true))
            ->pluck('id')
            ->map(fn (mixed $id): CheckXrayInboundHealthJob => new CheckXrayInboundHealthJob((int) $id))
            ->all();

        $inboundCount = count($jobs);
        $jobs[] = new CheckSubscriptionProfilesHealthJob();

        Bus::chain($jobs)
            ->catch(static function (Throwable $exception): void {
                report($exception);
                CheckSubscriptionProfilesHealthJob::dispatch();
            })
            ->dispatch();

        return $inboundCount;
    }
}
