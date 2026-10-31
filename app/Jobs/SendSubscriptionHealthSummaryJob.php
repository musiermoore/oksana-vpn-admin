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
        $checkedSince = now()->subMinutes(30);
        $profiles = SubscriptionProfileHealthCheck::query()
            ->where('checked_at', '>=', $checkedSince)
            ->get(['flow', 'name', 'status', 'error']);
        $inbounds = XrayInboundHealthCheck::query()
            ->where('checked_at', '>=', $checkedSince)
            ->with('inbound.server')
            ->get();

        if ($profiles->isEmpty() && $inbounds->isEmpty()) {
            return;
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
        $lines = $rows->take(30)->map(fn (array $row, string $name): string => sprintf(
            "%-24s | %s | %s | %s | %s | %s",
            Str::limit($name, 24, '…'),
            $this->matrixEmoji($row['direct']),
            $this->matrixEmoji($row['connect']),
            $this->matrixEmoji($row['connectV2']),
            $this->matrixEmoji($row['external']),
            $this->matrixEmoji($row['custom']),
        ))->all();
        array_unshift($lines, 'Имя                     | Direct | Connect | Connect v2 | External | Custom');
        if ($rows->count() > 30) {
            $lines[] = '…ещё '.($rows->count() - 30).' строк';
        }

        $causes = $profiles->pluck('error')->concat($inbounds->pluck('error'))
            ->filter()
            ->map(fn (string $error): string => $this->formatCause($error))
            ->unique()
            ->map(fn (string $cause): string => htmlspecialchars($cause, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            ->take(2)
            ->values()
            ->all();
        $causeSummary = $causes === [] ? '' : "\n\nПричины:\n• ".implode("\n• ", $causes);

        $warnings = $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_WARNING)->count()
            + $inbounds->where('status', XrayInboundHealthCheck::STATUS_WARNING)->count();
        $unhealthy = $profiles->where('status', SubscriptionProfileHealthCheck::STATUS_UNHEALTHY)->count()
            + $inbounds->where('status', XrayInboundHealthCheck::STATUS_UNHEALTHY)->count();
        $devChat->send(
            "🔎 Состояние VPN за последние 30 минут\n"
            ."🔴 Требуют исправления: {$unhealthy}\n"
            ."🟡 Нестабильны: {$warnings}\n\n"
            ."<pre>".htmlspecialchars(implode("\n", $lines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</pre>"
            .$causeSummary,
            'HTML',
        );
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

    private function matrixEmoji(string $status): string
    {
        return match ($status) {
            SubscriptionProfileHealthCheck::STATUS_HEALTHY => '✅',
            SubscriptionProfileHealthCheck::STATUS_WARNING => '⚠️',
            SubscriptionProfileHealthCheck::STATUS_UNHEALTHY => '❌',
            SubscriptionProfileHealthCheck::STATUS_PENDING => '⏳',
            SubscriptionProfileHealthCheck::STATUS_UNSUPPORTED => '➖',
            default => '—',
        };
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
