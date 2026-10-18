<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendSubscriptionHealthSummaryJob;
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
        $label = sprintf('%s / входящий порт #%s', $inbound->server?->name ?? 'Неизвестный сервер', $inbound->external_id);

        if ($health->status === XrayInboundHealthCheck::STATUS_WARNING && $previousStatus !== $health->status) {
            $this->queueHealthSummary();
        }

        if ($health->status === XrayInboundHealthCheck::STATUS_UNHEALTHY && $previousStatus !== $health->status) {
            $this->queueHealthSummary();
        }

        if ($health->status === XrayInboundHealthCheck::STATUS_HEALTHY
            && $previousStatus === XrayInboundHealthCheck::STATUS_UNHEALTHY) {
            $this->devChat->send("🟢 Входящий сервер восстановлен: {$label}");
        }
    }

    private function queueHealthSummary(): void
    {
        SendSubscriptionHealthSummaryJob::dispatch()
            ->delay(now()->addSeconds(10))
            ->afterCommit();
    }
}
