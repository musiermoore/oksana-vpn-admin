<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Subscription\NormalizedNode;
use App\Jobs\CheckSingleSubscriptionProfileHealthJob;
use App\Jobs\SendSubscriptionHealthSummaryJob;
use App\Models\SubscriptionProfileHealthCheck;
use Illuminate\Support\Str;
use Throwable;

final class SubscriptionProfileHealthRecorder
{
    /** @var array<string, array<int, string>> */
    private array $seenProfiles = [];

    public function __construct(private readonly TelegramDevChatService $devChat) {}

    public function runFlow(string $flow, callable $check): void
    {
        $this->seenProfiles[$flow] = [];

        try {
            $check();
        } catch (Throwable $exception) {
            report($exception);
            $this->record($flow, 'flow-error', 'Ошибка формирования: '.$this->flowName($flow), [
                'status' => SubscriptionProfileHealthCheck::STATUS_UNHEALTHY,
                'results' => [],
                'error' => Str::limit($exception->getMessage(), 500),
            ]);
        }

        SubscriptionProfileHealthCheck::query()
            ->where('flow', $flow)
            ->whereNotIn('profile_key', $this->seenProfiles[$flow])
            ->delete();
    }

    /** @param callable():array{status:string,results:array<string,mixed>,error:?string} $probe */
    public function recordProbe(string $flow, string $key, string $name, callable $probe): void
    {
        try {
            $this->record($flow, $key, $name, $probe());
        } catch (Throwable $exception) {
            report($exception);
            $this->record($flow, $key, $name, [
                'status' => SubscriptionProfileHealthCheck::STATUS_UNHEALTHY,
                'results' => [],
                'error' => Str::limit($exception->getMessage(), 500),
            ]);
        }
    }

    /** @param array<string,mixed>|NormalizedNode $subject */
    public function queueProbe(string $flow, string $key, string $name, string $mode, array|NormalizedNode $subject): void
    {
        $check = SubscriptionProfileHealthCheck::query()->firstOrNew([
            'flow' => $flow,
            'profile_key' => $key,
        ]);
        $this->seenProfiles[$flow] ??= [];
        $this->seenProfiles[$flow][] = $key;

        if (! $check->exists || in_array($check->status, [
            SubscriptionProfileHealthCheck::STATUS_PENDING,
            SubscriptionProfileHealthCheck::STATUS_UNKNOWN,
        ], true)) {
            $this->record($flow, $key, $name, [
                'status' => SubscriptionProfileHealthCheck::STATUS_PENDING,
                'results' => [],
                'error' => 'Waiting for a probe worker.',
            ]);
        }

        CheckSingleSubscriptionProfileHealthJob::dispatch($flow, $key, $name, $mode, $subject)
            ->afterCommit();
    }

    /** @param array{status:string,results:array<string,mixed>,error:?string} $result */
    public function record(string $flow, string $key, string $name, array $result): void
    {
        $this->seenProfiles[$flow] ??= [];
        $this->seenProfiles[$flow][] = $key;
        $check = SubscriptionProfileHealthCheck::query()->firstOrNew([
            'flow' => $flow,
            'profile_key' => $key,
        ]);
        $previousStatus = $check->status;
        $check->fill([
            'name' => Str::limit($name, 255, ''),
            'status' => $result['status'],
            'results' => $result['results'],
            'error' => $result['error'],
            'checked_at' => now(),
        ])->save();

        $this->notifyOnTransition($flow, $check, $previousStatus);
    }

    public function recordEmpty(string $flow, array $items): void
    {
        if ($items !== []) {
            return;
        }

        $this->record($flow, 'no-profiles', 'No eligible profiles', [
            'status' => SubscriptionProfileHealthCheck::STATUS_UNKNOWN,
            'results' => [],
            'error' => 'The technical user currently receives no profiles for this flow.',
        ]);
    }

    private function notifyOnTransition(string $flow, SubscriptionProfileHealthCheck $check, ?string $previousStatus): void
    {
        if (in_array($check->status, [
            SubscriptionProfileHealthCheck::STATUS_WARNING,
            SubscriptionProfileHealthCheck::STATUS_UNHEALTHY,
        ], true) && $previousStatus !== $check->status) {
            SendSubscriptionHealthSummaryJob::dispatch()
                ->delay(now()->addSeconds(10))
                ->afterCommit();
        }

        if ($check->status === SubscriptionProfileHealthCheck::STATUS_HEALTHY
            && in_array($previousStatus, [
                SubscriptionProfileHealthCheck::STATUS_WARNING,
                SubscriptionProfileHealthCheck::STATUS_UNHEALTHY,
            ], true)) {
            $this->devChat->send("🟢 Профиль восстановлен: {$this->flowName($flow)} — {$check->name}");
        }
    }

    private function flowName(string $flow): string
    {
        return match ($flow) {
            'connect' => '/connect',
            'connect-v2' => '/connect-v2',
            'external' => 'внешняя подписка',
            'external-source' => 'синхронизация внешних подписок',
            'xray-custom' => 'пользовательская подписка Xray',
            default => $flow,
        };
    }
}
