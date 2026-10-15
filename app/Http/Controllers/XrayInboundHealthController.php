<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CheckXrayInboundHealthJob;
use App\Models\XrayInbound;
use App\Models\XrayInboundHealthCheck;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class XrayInboundHealthController
{
    public function index(): Response
    {
        $inbounds = XrayInbound::query()
            ->where('is_active', true)
            ->whereHas('server', fn ($query) => $query->where('is_active', true)->where('is_ready', true))
            ->with(['server:id,name,code', 'healthCheck'])
            ->get()
            ->map(fn (XrayInbound $inbound): array => [
                'id' => $inbound->id,
                'server' => $inbound->server?->name,
                'server_code' => $inbound->server?->code,
                'external_id' => $inbound->external_id,
                'protocol' => (string) data_get($inbound->params, 'protocol', 'unknown'),
                'remark' => (string) data_get($inbound->params, 'remark', ''),
                'status' => $inbound->healthCheck?->status ?? XrayInboundHealthCheck::STATUS_UNKNOWN,
                'checked_at' => $inbound->healthCheck?->checked_at?->toIso8601String(),
                'error' => $inbound->healthCheck?->error,
                'results' => $inbound->healthCheck?->results ?? [],
            ])
            ->values();

        return Inertia::render('XrayInboundHealth/Index', [
            'inbounds' => $inbounds,
            'summary' => [
                'total' => $inbounds->count(),
                'healthy' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_HEALTHY)->count(),
                'unhealthy' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_UNHEALTHY)->count(),
                'unsupported' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_UNSUPPORTED)->count(),
                'unknown' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_UNKNOWN)->count(),
            ],
        ]);
    }

    public function run(): RedirectResponse
    {
        XrayInbound::query()
            ->where('is_active', true)
            ->whereHas('server', fn ($query) => $query->where('is_active', true)->where('is_ready', true))
            ->pluck('id')
            ->each(fn (int $id) => CheckXrayInboundHealthJob::dispatch($id)->afterCommit());

        return redirect()->route('xray-inbound-health.index')->with('success', 'Health checks queued. Results will appear as the probes finish.');
    }
}
