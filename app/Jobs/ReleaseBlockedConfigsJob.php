<?php

namespace App\Jobs;

use App\Services\DeviceLimitService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReleaseBlockedConfigsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 55;

    public function __construct()
    {
        $this->onQueue('xui-sync');
    }

    public function uniqueId(): string
    {
        return 'release-blocked-configs';
    }

    public function handle(DeviceLimitService $deviceLimitService): void
    {
        $deviceLimitService->releaseExpiredBlocks();
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
