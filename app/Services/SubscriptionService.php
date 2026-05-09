<?php

namespace App\Services;

use App\Models\PaymentPeriod;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    private const RENEWAL_REMINDER_COOLDOWN_HOURS = 24;

    public function __construct(
        private readonly TelegramBroadcastService $telegramBroadcastService,
    ) {}

    public function renewEligibleSubscriptions(): void
    {
        $users = User::query()
            ->with('latestSubscription')
            ->get();

        foreach ($users as $user) {
            $this->renewForUser($user);
        }
    }

    public function renewForUser(User $user): void
    {
        $latestSubscription = $user->latestSubscription;
        $activePeriod = PaymentPeriod::getActive();

        if (! $latestSubscription || ! $activePeriod || ! $this->isRenewalDue($latestSubscription)) {
            return;
        }

        $amount = $this->getRenewalAmount($user, $activePeriod->id, (float) $activePeriod->amount);

        if (! $this->userHasEnoughBalance($user, $amount)) {
            return;
        }

        $startDate = Carbon::parse($latestSubscription->end_date)->addDay()->toDateString();
        $endDate = Carbon::parse($startDate)->addMonth()->toDateString();

        $createdSubscription = $this->createSubscriptionIfMissing($user, $startDate, $endDate, $amount);

        if (! $createdSubscription?->wasRecentlyCreated) {
            return;
        }

        $this->notifyAboutSuccessfulRenewal($user, $createdSubscription);
    }

    public function sendRenewalReminders(): void
    {
        $users = User::query()
            ->with('latestSubscription')
            ->get();

        foreach ($users as $user) {
            $this->sendRenewalReminderForUser($user);
        }
    }

    private function sendRenewalReminderForUser(User $user): void
    {
        $latestSubscription = $user->latestSubscription;
        $activePeriod = PaymentPeriod::getActive();

        if (! $latestSubscription || ! $activePeriod || ! $this->isRenewalDue($latestSubscription)) {
            return;
        }

        $amount = $this->getRenewalAmount($user, $activePeriod->id, (float) $activePeriod->amount);

        if ($this->userHasEnoughBalance($user, $amount) || ! $this->shouldSendReminder($latestSubscription)) {
            return;
        }

        if (! $this->telegramBroadcastService->sendToSingleUser(
            $user,
            "You don't have enough money to renew the subscription"
        )) {
            return;
        }

        $latestSubscription->forceFill([
            'renewal_reminder_sent_at' => now(),
        ])->save();
    }

    private function createSubscriptionIfMissing(
        User $user,
        string $startDate,
        string $endDate,
        float $price
    ): ?UserSubscription {
        $createdSubscription = null;

        DB::transaction(function () use ($user, $startDate, $endDate, $price, &$createdSubscription) {
            $subscription = UserSubscription::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                [
                    'price' => $price,
                ]
            );

            if (! $subscription->wasRecentlyCreated) {
                $createdSubscription = $subscription;

                return;
            }

            $user->transactions()->create([
                'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
                'amount' => -$price,
                'is_approved' => true,
                'description' => 'Продление подписки',
            ]);

            $createdSubscription = $subscription;
        });

        return $createdSubscription;
    }

    private function getRenewalAmount(User $user, int $paymentPeriodId, float $baseAmount): float
    {
        $extraAmount = (float) $user->extraPayments()
            ->where('current_payment_id', $paymentPeriodId)
            ->sum('amount');

        return $baseAmount + $extraAmount;
    }

    private function userHasEnoughBalance(User $user, float $requiredAmount): bool
    {
        return $user->getBalanceAmount() >= $requiredAmount;
    }

    private function isRenewalDue(UserSubscription $latestSubscription): bool
    {
        $renewalDate = Carbon::parse($latestSubscription->end_date)->subDay()->startOfDay();

        return now()->greaterThanOrEqualTo($renewalDate);
    }

    private function shouldSendReminder(UserSubscription $latestSubscription): bool
    {
        if ($latestSubscription->renewal_reminder_sent_at === null) {
            return true;
        }

        return $latestSubscription->renewal_reminder_sent_at
            ->copy()
            ->addHours(self::RENEWAL_REMINDER_COOLDOWN_HOURS)
            ->lessThanOrEqualTo(now());
    }

    private function notifyAboutSuccessfulRenewal(User $user, UserSubscription $subscription): void
    {
        if ($subscription->renewal_success_notified_at !== null) {
            return;
        }

        if (! $this->telegramBroadcastService->sendToSingleUser(
            $user,
            'Your subscription has been renewed successfully.'
        )) {
            return;
        }

        $subscription->forceFill([
            'renewal_success_notified_at' => now(),
        ])->save();
    }
}
