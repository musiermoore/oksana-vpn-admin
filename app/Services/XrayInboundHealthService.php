<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\XrayInbound;
use App\Models\XrayInboundHealthCheck;
use Illuminate\Support\Str;
use Throwable;

final class XrayInboundHealthService
{
    public function __construct(
        private readonly XrayInboundProbeProvisioner $probeProvisioner,
        private readonly TelegramDevChatService $devChat,
    ) {}

    public function check(XrayInbound $inbound): XrayInboundHealthCheck
    {
        $previousStatus = $inbound->healthCheck?->status;

        try {
            $result = $this->probeProvisioner->check($inbound);
        } catch (Throwable $exception) {
            report($exception);
            $result = [
                'status' => XrayInboundHealthCheck::STATUS_UNHEALTHY,
                'results' => [],
                'error' => Str::limit($exception->getMessage(), 500),
            ];
        }

        $health = XrayInboundHealthCheck::query()->updateOrCreate(
            ['xray_inbound_id' => $inbound->id],
            [
                'status' => $result['status'],
                'results' => $result['results'],
                'error' => $result['error'],
                'checked_at' => now(),
            ],
        );

        $this->notifyOnTransition($inbound, $previousStatus, $health);

        return $health;
    }

    private function notifyOnTransition(XrayInbound $inbound, ?string $previousStatus, XrayInboundHealthCheck $health): void
    {
        $label = sprintf('%s / inbound %d', $inbound->server?->name ?? 'Unknown server', $inbound->external_id);

        if ($health->status === XrayInboundHealthCheck::STATUS_UNHEALTHY && $previousStatus !== $health->status) {
            $this->devChat->send("⚠️ Inbound health check failed: {$label}\n".($health->error ?? 'One or more destinations failed.')."\nPlease hide it from connect or fix the routing.");
        }

        if ($health->status === XrayInboundHealthCheck::STATUS_HEALTHY && $previousStatus === XrayInboundHealthCheck::STATUS_UNHEALTHY) {
            $this->devChat->send("✅ Inbound recovered: {$label}");
        }
    }
}
