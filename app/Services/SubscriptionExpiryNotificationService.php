<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendTelegramMessageJob;
use App\Models\SubscriptionExpiryNotification;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class SubscriptionExpiryNotificationService
{
    /**
     * @var array<int, array{key:string,hours:int,time_left:string}>
     */
    private const THRESHOLDS = [
        ['key' => '3d', 'hours' => 72, 'time_left' => '3 дня'],
        ['key' => '2d', 'hours' => 48, 'time_left' => '2 дня'],
        ['key' => '1d', 'hours' => 24, 'time_left' => '1 день'],
        ['key' => '6h', 'hours' => 6, 'time_left' => '6 часов'],
    ];

    public function __construct(
        private readonly TelegramMiniAppLinkService $miniAppLinks,
    ) {}

    public function sendDueNotifications(): void
    {
        $users = User::query()
            ->whereNotNull('telegram_id')
            ->with('latestActiveOrFutureSubscription')
            ->get();

        foreach ($users as $user) {
            $this->sendDueNotificationsForUser($user);
        }
    }

    public function sendDueNotificationsForUser(User $user): void
    {
        $freshUser = User::query()
            ->with('latestActiveOrFutureSubscription')
            ->find($user->id);

        $subscription = $freshUser?->latestActiveOrFutureSubscription;

        if (! $freshUser || ! $subscription) {
            return;
        }

        $threshold = $this->resolveDueThreshold($subscription);

        if ($threshold === null) {
            return;
        }

        $expiryAt = $this->resolveSubscriptionEndAt($subscription);

        try {
            SubscriptionExpiryNotification::query()->create([
                'user_id' => $freshUser->id,
                'user_subscription_id' => $subscription->id,
                'threshold_key' => $threshold['key'],
                'threshold_hours' => $threshold['hours'],
                'subscription_end_at' => $expiryAt,
                'sent_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateThresholdException($exception)) {
                return;
            }

            throw $exception;
        }

        SendTelegramMessageJob::dispatch([
            'chat_id' => (string) $freshUser->telegram_id,
            'text' => $this->buildMessage($threshold['key'], $expiryAt),
        ]);
    }

    /**
     * @return array{key:string,hours:int,time_left:string}|null
     */
    public function resolveDueThreshold(UserSubscription $subscription): ?array
    {
        $now = now();
        $endDate = Carbon::parse($subscription->end_date)->startOfDay();
        $expiryAt = $this->resolveSubscriptionEndAt($subscription);

        if ($expiryAt->lessThanOrEqualTo($now)) {
            return null;
        }

        return match (true) {
            $now->isSameDay($endDate->copy()->subDays(3)) => self::THRESHOLDS[0],
            $now->isSameDay($endDate->copy()->subDays(2)) => self::THRESHOLDS[1],
            $now->isSameDay($endDate->copy()->subDay()) => self::THRESHOLDS[2],
            $now->greaterThanOrEqualTo($expiryAt->copy()->subHours(6)) => self::THRESHOLDS[3],
            default => null,
        };
    }

    public function buildMessage(string $thresholdKey, Carbon $expiryAt): string
    {
        $timeLeft = $this->thresholdMap()[$thresholdKey]['time_left'] ?? $thresholdKey;
        $formattedExpiry = $thresholdKey === '6h'
            ? $expiryAt->format('d.m.Y H:i')
            : $expiryAt->format('d.m.Y');

        return sprintf(
            "Ваша подписка на VPN закончится через %s — %s.\n\nЧтобы доступ не прервался, продлите подписку в мини-приложении:\n%s",
            $timeLeft,
            $formattedExpiry,
            $this->miniAppLinks->payments()
        );
    }

    public function resolveSubscriptionEndAt(UserSubscription $subscription): Carbon
    {
        return Carbon::parse($subscription->end_date)->endOfDay();
    }

    /**
     * @return array<string, array{key:string,hours:int,time_left:string}>
     */
    private function thresholdMap(): array
    {
        return collect(self::THRESHOLDS)
            ->keyBy('key')
            ->all();
    }

    private function isDuplicateThresholdException(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
