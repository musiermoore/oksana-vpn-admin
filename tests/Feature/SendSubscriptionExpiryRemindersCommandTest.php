<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendTelegramMessageJob;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendSubscriptionExpiryRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram.bot_username', 'OksanaVpnBot');
    }

    public function test_command_dispatches_three_day_expiry_reminder_once_per_subscription_period(): void
    {
        Carbon::setTestNow('2026-08-22 12:00:00');
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456',
        ]);

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-07-26',
            'end_date' => '2026-08-25',
            'price' => 100,
        ]);

        $this->artisan('subscriptions:send-expiry-reminders')
            ->assertSuccessful();

        $this->artisan('subscriptions:send-expiry-reminders')
            ->assertSuccessful();

        Queue::assertPushed(SendTelegramMessageJob::class, 1);
        Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job) use ($user): bool {
            return $job->payload['chat_id'] === (string) $user->telegram_id
                && $job->payload['text'] === "Ваша подписка на VPN закончится через 3 дня — 25.08.2026.\n\nЧтобы доступ не прервался, продлите подписку в мини-приложении:\nhttps://t.me/OksanaVpnBot?startapp=payments";
        });

        $this->assertDatabaseHas('subscription_expiry_notifications', [
            'user_id' => $user->id,
            'user_subscription_id' => $subscription->id,
            'threshold_key' => '3d',
            'threshold_hours' => 72,
        ]);

        $this->assertDatabaseCount('subscription_expiry_notifications', 1);
    }

    public function test_command_does_not_send_follow_up_notifications_for_old_period_after_renewal(): void
    {
        Carbon::setTestNow('2026-08-22 12:00:00');
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'telegram_id' => '654321',
        ]);

        $currentSubscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-07-26',
            'end_date' => '2026-08-25',
            'price' => 100,
        ]);

        $this->artisan('subscriptions:send-expiry-reminders')
            ->assertSuccessful();

        Queue::assertPushed(SendTelegramMessageJob::class, 1);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-26',
            'end_date' => '2026-09-25',
            'price' => 100,
        ]);

        Carbon::setTestNow('2026-08-23 12:00:00');
        Queue::fake();

        $this->artisan('subscriptions:send-expiry-reminders')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('subscription_expiry_notifications', [
            'user_id' => $user->id,
            'user_subscription_id' => $currentSubscription->id,
            'threshold_key' => '3d',
        ]);
        $this->assertDatabaseMissing('subscription_expiry_notifications', [
            'user_id' => $user->id,
            'user_subscription_id' => $currentSubscription->id,
            'threshold_key' => '2d',
        ]);
        $this->assertDatabaseCount('subscription_expiry_notifications', 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
