<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SubscriptionProfileHealthCheck;
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
        $failures = SubscriptionProfileHealthCheck::query()
            ->where('status', SubscriptionProfileHealthCheck::STATUS_UNHEALTHY)
            ->where('checked_at', '>=', now()->subMinutes(20))
            ->orderBy('flow')
            ->orderBy('name')
            ->get(['flow', 'name', 'error']);

        if ($failures->isEmpty()) {
            return;
        }

        $lines = $failures->take(15)
            ->map(fn (SubscriptionProfileHealthCheck $failure): string => sprintf(
                '• %s / %s',
                $failure->flow,
                Str::limit($failure->name, 120),
            ))
            ->all();

        if ($failures->count() > count($lines)) {
            $lines[] = '• … and '.($failures->count() - count($lines)).' more';
        }

        $causes = $failures->pluck('error')
            ->filter()
            ->map(fn (string $error): string => str_contains(mb_strtolower($error), 'geodata')
                || str_contains(mb_strtolower($error), 'geoip')
                || str_contains(mb_strtolower($error), 'geosite')
                    ? 'Xray routing/geodata validation failed; verify rules and required .dat assets.'
                    : Str::limit($error, 180))
            ->unique()
            ->take(2)
            ->values()
            ->all();
        $causeSummary = $causes === [] ? '' : "\nCause(s):\n• ".implode("\n• ", $causes);

        $devChat->send("⚠️ Subscription health: {$failures->count()} unhealthy profile(s)\n".implode("\n", $lines).$causeSummary);
    }
}
