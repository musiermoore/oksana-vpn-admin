<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Subscription\NormalizedNode;
use App\Jobs\CheckSingleSubscriptionProfileHealthJob;
use App\Models\SubscriptionProfileHealthCheck;
use Illuminate\Support\Str;
use Throwable;

final class SubscriptionProfileHealthRecorder
{
    /** @var array<string, array<int, string>> */
    private array $seenProfiles = [];

    public function runFlow(string $flow, callable $check): void
    {
        $this->seenProfiles[$flow] = [];

        try {
            $check();
        } catch (Throwable $exception) {
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
                'error' => 'Ожидает выполнения проверки.',
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
        $check->fill([
            'name' => Str::limit($name, 255, ''),
            'status' => $result['status'],
            'results' => $result['results'],
            'error' => $result['error'],
            'checked_at' => now(),
        ])->save();

    }

    public function recordEmpty(string $flow, array $items): void
    {
        if ($items !== []) {
            return;
        }

        $this->record($flow, 'no-profiles', 'No eligible profiles', [
            'status' => SubscriptionProfileHealthCheck::STATUS_UNKNOWN,
            'results' => [],
            'error' => 'Технический пользователь пока не получает профили для этого типа подписки.',
        ]);
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
