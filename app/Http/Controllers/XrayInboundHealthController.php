<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\HealthCheck\UpdateInboundHealthCheckSettingsRequest;
use App\Models\XrayInbound;
use App\Models\XrayInboundHealthCheck;
use App\Models\XrayJsonSetting;
use App\Models\SubscriptionProfileHealthCheck;
use App\Models\User;
use App\Services\InboundHealthCheckScheduler;
use App\Services\InboundHealthCheckSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class XrayInboundHealthController
{
    public function index(InboundHealthCheckSettings $settings): Response
    {
        $technicalUser = User::query()
            ->where('is_health_check_user', true)
            ->first(['id', 'name', 'telegram_id']);
        $inbounds = $this->inboundPayloads($technicalUser);
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
            'technicalUser' => $technicalUser?->only(['name', 'telegram_id']),
            'profileSummary' => [
                'total' => $profiles->count(),
                'healthy' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_HEALTHY)->count(),
                'warning' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_WARNING)->count(),
                'unhealthy' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_UNHEALTHY)->count(),
                'unsupported' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_UNSUPPORTED)->count(),
                'pending' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_PENDING)->count(),
                'unknown' => $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_UNKNOWN)->count(),
            ],
            'profileFlowSummary' => [
                'connect' => $profiles->where('flow', 'connect')->count(),
                'connectV2' => $profiles->where('flow', 'connect-v2')->count(),
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
            'healthChecksEnabled' => $settings->enabled(),
        ]);
    }

    private function inboundPayloads(?User $technicalUser): Collection
    {
        return XrayInbound::query()
            ->where('is_active', true)
            ->whereHas('server', fn ($query) => $query->where('is_active', true)->where('is_ready', true))
            ->with([
                'server:id,name,code',
                'healthCheck',
                'vlessConfigs' => fn ($query) => $query
                    ->where('user_id', $technicalUser?->id ?? 0)
                    ->select(['id', 'xray_inbound_id', 'user_id', 'is_active', 'enable']),
            ])
            ->get()
            ->map(function (XrayInbound $inbound): array {
                $hasClientConfig = $inbound->vlessConfigs->contains(
                    fn ($config): bool => $config->is_active && $config->enable,
                );
                $inclusionStatus = fn (bool $isVisible): string => ! $inbound->is_public || ! $isVisible
                    ? 'hidden'
                    : ($hasClientConfig ? 'included' : 'no-config');

                return [
                    'id' => $inbound->id,
                    'server' => $inbound->server?->name,
                    'server_code' => $inbound->server?->code,
                    'external_id' => $inbound->external_id,
                    'protocol' => (string) data_get($inbound->params, 'protocol', 'unknown'),
                    'remark' => (string) data_get($inbound->params, 'remark', ''),
                    'connect_inclusion' => $inclusionStatus($inbound->is_visible_in_connect),
                    'connect_v2_inclusion' => $inclusionStatus($inbound->is_visible_in_connect_v2),
                    'status' => $inbound->healthCheck?->status ?? XrayInboundHealthCheck::STATUS_UNKNOWN,
                    'checked_at' => $inbound->healthCheck?->checked_at?->toIso8601String(),
                    'error' => $inbound->healthCheck?->error,
                    'results' => $inbound->healthCheck?->results ?? [],
                ];
            })
            ->values();
    }

    public function run(InboundHealthCheckScheduler $scheduler, InboundHealthCheckSettings $settings): RedirectResponse
    {
        if (! $settings->enabled()) {
            return redirect()->route('xray-inbound-health.index')->with('success', 'Health checks are disabled. Enable them to queue a run.');
        }

        $scheduler->dispatch();

        return redirect()->route('xray-inbound-health.index')->with('success', 'Health checks queued. Results will appear as the probes finish.');
    }

    public function updateSettings(
        UpdateInboundHealthCheckSettingsRequest $request,
        InboundHealthCheckSettings $settings,
    ): RedirectResponse
    {
        $data = $request->toData();
        $settings->setEnabled($data->is_enabled);

        return redirect()->route('xray-inbound-health.index')->with(
            'success',
            $data->is_enabled ? 'Health checks enabled.' : 'Health checks disabled.',
        );
    }
}
