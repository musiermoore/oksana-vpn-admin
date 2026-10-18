<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\XrayInbound;
use App\Services\InboundHealthCheckSettings;
use App\Services\XrayInboundHealthService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CheckXrayInboundHealthJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $inboundId) {}

    public function uniqueId(): string
    {
        return (string) $this->inboundId;
    }

    public function handle(XrayInboundHealthService $healthService, InboundHealthCheckSettings $settings): void
    {
        if (! $settings->enabled()) {
            return;
        }

        $inbound = XrayInbound::query()
            ->with(['server', 'healthCheck'])
            ->find($this->inboundId);

        if (! $inbound || ! $inbound->is_active || ! $inbound->server?->is_active || ! $inbound->server?->is_ready) {
            return;
        }

        $healthService->check($inbound);
    }
}
