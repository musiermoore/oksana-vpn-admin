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
    ) {}

    public function check(XrayInbound $inbound): XrayInboundHealthCheck
    {
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

        return $health;
    }
}
