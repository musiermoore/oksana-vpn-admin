<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\XrayInbound;
use App\Models\XrayInboundHealthCheck;
use App\Models\XrayJsonSetting;
use App\Models\SubscriptionProfileHealthCheck;
use App\Models\User;
use App\Services\InboundHealthCheckScheduler;
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
        $profiles = SubscriptionProfileHealthCheck::query()
            ->orderBy('flow')
            ->orderBy('name')
            ->get()
            ->map(fn (SubscriptionProfileHealthCheck $profile): array => [
                'id' => $profile->id,
                'flow' => $profile->flow,
                'name' => $profile->name,
                'status' => $profile->status,
                'checked_at' => $profile->checked_at?->toIso8601String(),
                'error' => $profile->error,
                'results' => $profile->results ?? [],
            ])
            ->values();

        return Inertia::render('XrayInboundHealth/Index', [
            'inbounds' => $inbounds,
            'profiles' => $profiles,
            'technicalUser' => User::query()
                ->where('is_health_check_user', true)
                ->first(['name', 'telegram_id'])
                ?->only(['name', 'telegram_id']),
            'profileSummary' => [
                'total' => $profiles->count(),
                'healthy' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_HEALTHY)->count(),
                'warning' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_WARNING)->count(),
                'unhealthy' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_UNHEALTHY)->count(),
                'unsupported' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_UNSUPPORTED)->count(),
                'pending' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_PENDING)->count(),
                'unknown' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_UNKNOWN)->count(),
            ],
            'summary' => [
                'total' => $inbounds->count(),
                'healthy' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_HEALTHY)->count(),
                'warning' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_WARNING)->count(),
                'unhealthy' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_UNHEALTHY)->count(),
                'unsupported' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_UNSUPPORTED)->count(),
                'unknown' => $inbounds->where('status', XrayInboundHealthCheck::STATUS_UNKNOWN)->count(),
            ],
            'globalJsonSettings' => [
                'active' => XrayJsonSetting::query()->active()->count(),
                'applied_name' => XrayJsonSetting::query()->latestActive()->value('name'),
            ],
        ]);
    }

    public function run(InboundHealthCheckScheduler $scheduler): RedirectResponse
    {
        $scheduler->dispatch();

        return redirect()->route('xray-inbound-health.index')->with('success', 'Health checks queued. Results will appear as the probes finish.');
    }
}
