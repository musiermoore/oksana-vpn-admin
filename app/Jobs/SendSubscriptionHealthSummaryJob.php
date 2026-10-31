<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SubscriptionProfileHealthCheck;
use App\Models\XrayInboundHealthCheck;
use App\Services\HealthCheckSummaryImageRenderer;
use App\Services\TelegramDevChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

final class SendSubscriptionHealthSummaryJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor = 120;

    public function __construct(public bool $includeStaleResults = false) {}

    public function uniqueId(): string
    {
        return 'subscription-health-failure-summary'.($this->includeStaleResults ? '-manual' : '');
    }

    public function handle(TelegramDevChatService $devChat, HealthCheckSummaryImageRenderer $imageRenderer): void
    {
        $checkedSince = now()->subMinutes(30);
        $profilesQuery = SubscriptionProfileHealthCheck::query();
        $inboundsQuery = XrayInboundHealthCheck::query()->with('inbound.server');
        if (! $this->includeStaleResults) {
            $profilesQuery->where('checked_at', '>=', $checkedSince);
            $inboundsQuery->where('checked_at', '>=', $checkedSince);
        }
        $profiles = $profilesQuery->get(['flow', 'name', 'status', 'error']);
        $inbounds = $inboundsQuery->get();

        if ($profiles->isEmpty() && $inbounds->isEmpty()) {
            if (! $this->includeStaleResults) {
                return;
            }
        }

        $rows = [];
        foreach ($inbounds as $inbound) {
            $this->addMatrixStatus($rows, (string) ($inbound->inbound?->server?->name ?? 'Неизвестный сервер'), 'direct', $inbound->status);
        }
        foreach ($profiles as $profile) {
            $column = match ($profile->flow) {
                'connect' => 'connect',
                'connect-v2' => 'connectV2',
                'external', 'external-source' => 'external',
                'xray-custom' => 'custom',
                default => null,
            };
            if ($column !== null) {
                $this->addMatrixStatus($rows, $profile->name, $column, $profile->status);
            }
        }

        $rows = collect($rows)->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);

        $causes = $profiles->pluck('error')->concat($inbounds->pluck('error'))
            ->filter()
            ->map(fn (string $error): string => $this->formatCause($error))
            ->unique()
            ->take(2)
            ->values()
            ->all();
        $causeSummary = $causes === [] ? '' : "\n\nПричины:\n• ".implode("\n• ", $causes);

        $warnings = $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_WARNING)->count()
            + $inbounds->where('status', XrayInboundHealthCheck::STATUS_WARNING)->count();
        $unhealthy = $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_UNHEALTHY)->count()
            + $inbounds->where('status', XrayInboundHealthCheck::STATUS_UNHEALTHY)->count();
        $caption = ($this->includeStaleResults ? '🔎 Текущее состояние VPN' : '🔎 Состояние VPN за последние 30 минут')
            ."\n🔴 Требуют исправления: {$unhealthy}"
            ."\n🟡 Нестабильны: {$warnings}"
            .$causeSummary;
        $image = $imageRenderer->render(
            $rows->all(),
            $this->includeStaleResults,
            $unhealthy,
            $warnings,
        );
        $devChat->sendPhoto($image, $caption);
    }

    /** @param array<string, array<string, string>> $rows */
    private function addMatrixStatus(array &$rows, string $name, string $column, string $status): void
    {
        $label = trim(explode(' • ', $name, 2)[0]) ?: 'Неизвестный узел';
        $rows[$label] ??= array_fill_keys(['direct', 'connect', 'connectV2', 'external', 'custom'], SubscriptionProfileHealthCheck::STATUS_UNKNOWN);
        $current = $rows[$label][$column];
        $priority = ['unhealthy' => 0, 'warning' => 1, 'pending' => 2, 'healthy' => 3, 'unsupported' => 4, 'unknown' => 5];
        if (($priority[$status] ?? 5) < ($priority[$current] ?? 5)) {
            $rows[$label][$column] = $status;
        }
    }

    private function formatCause(string $error): string
    {
        $lowerError = mb_strtolower($error);

        if (str_contains($lowerError, 'geodata') || str_contains($lowerError, 'geoip') || str_contains($lowerError, 'geosite')) {
            return 'Ошибка правил геомаршрутизации. Проверьте geoip.dat/geosite.dat и правила Xray.';
        }

        if (str_starts_with($error, 'Partially reachable; failed destinations:')) {
            return 'Часть сайтов доступна. Не отвечают: '.Str::after($error, 'Partially reachable; failed destinations:');
        }

        if (str_starts_with($error, 'Failed destinations:')) {
            return 'Не удалось подключиться к сайтам: '.Str::after($error, 'Failed destinations:');
        }

        return 'Техническая ошибка: '.Str::limit($error, 180);
    }
}
