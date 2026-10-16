<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SubscriptionProfileHealthCheck;
use App\Models\XrayInboundHealthCheck;
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

    public function uniqueId(): string
    {
        return 'subscription-health-failure-summary';
    }

    public function handle(TelegramDevChatService $devChat): void
    {
        $checkedSince = now()->subMinutes(20);
        $profileFailures = SubscriptionProfileHealthCheck::query()
            ->whereIn('status', [
                SubscriptionProfileHealthCheck::STATUS_WARNING,
                SubscriptionProfileHealthCheck::STATUS_UNHEALTHY,
            ])
            ->where('checked_at', '>=', $checkedSince)
            ->get(['flow', 'name', 'status', 'error']);
        $inboundFailures = XrayInboundHealthCheck::query()
            ->whereIn('status', [
                XrayInboundHealthCheck::STATUS_WARNING,
                XrayInboundHealthCheck::STATUS_UNHEALTHY,
            ])
            ->where('checked_at', '>=', $checkedSince)
            ->with('inbound.server')
            ->get();

        $failures = $profileFailures
            ->map(fn (SubscriptionProfileHealthCheck $failure): array => [
                'flow' => $this->flowName($failure->flow),
                'name' => $failure->name,
                'status' => $failure->status,
                'error' => $failure->error,
            ])
            ->concat($inboundFailures->map(fn (XrayInboundHealthCheck $failure): array => [
                'flow' => 'Прямое подключение',
                'name' => sprintf(
                    '%s / входящий порт #%s',
                    $failure->inbound?->server?->name ?? 'Неизвестный сервер',
                    $failure->inbound?->external_id ?? '?',
                ),
                'status' => $failure->status,
                'error' => $failure->error,
            ]))
            ->sortBy(fn (array $failure): array => [
                $failure['status'] === SubscriptionProfileHealthCheck::STATUS_UNHEALTHY ? 0 : 1,
                mb_strtolower($failure['flow']),
                mb_strtolower($failure['name']),
            ])
            ->values();

        if ($failures->isEmpty()) {
            return;
        }

        $lines = $failures->take(15)
            ->map(fn (array $failure): string => sprintf(
                '%s %s — %s',
                $this->statusEmoji($failure['status']),
                $failure['flow'],
                Str::limit($failure['name'], 120),
            ))
            ->all();

        if ($failures->count() > count($lines)) {
            $lines[] = '• И ещё '.($failures->count() - count($lines));
        }

        $causes = $failures->pluck('error')
            ->filter()
            ->map(fn (string $error): string => $this->formatCause($error))
            ->unique()
            ->take(2)
            ->values()
            ->all();
        $causeSummary = $causes === [] ? '' : "\n\nПричины:\n• ".implode("\n• ", $causes);

        $warnings = $failures->where('status', SubscriptionProfileHealthCheck::STATUS_WARNING)->count();
        $unhealthy = $failures->where('status', SubscriptionProfileHealthCheck::STATUS_UNHEALTHY)->count();
        $devChat->send(
            "🔎 Состояние VPN за последние 20 минут\n"
            ."🔴 Требуют исправления: {$unhealthy}\n"
            ."🟡 Нестабильны: {$warnings}\n\n"
            .implode("\n", $lines)
            .$causeSummary
        );
    }

    private function flowName(string $flow): string
    {
        return match ($flow) {
            'connect' => '/connect',
            'connect-v2' => '/connect-v2',
            'external' => 'Внешняя подписка',
            'external-source' => 'Синхронизация внешних подписок',
            'xray-custom' => 'Пользовательская подписка Xray',
            default => $flow,
        };
    }

    private function statusEmoji(string $status): string
    {
        return $status === SubscriptionProfileHealthCheck::STATUS_UNHEALTHY ? '🔴' : '🟡';
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
