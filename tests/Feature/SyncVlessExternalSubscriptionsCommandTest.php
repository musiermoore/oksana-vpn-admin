<?php

namespace Tests\Feature;

use App\Console\Commands\SyncVlessExternalSubscriptionsCommand;
use App\Jobs\SyncVlessExternalSubscriptionJob;
use App\Models\User;
use App\Models\VlessExternalSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SyncVlessExternalSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_jobs_only_for_active_subscriptions(): void
    {
        Queue::fake();

        $active = VlessExternalSubscription::query()->create([
            'name' => 'Active WL',
            'type' => VlessExternalSubscription::TYPE_DIRECT,
            'source_url' => 'vless://uuid@active.example.com:443?type=tcp&security=reality#active',
            'is_active' => true,
            'is_ready' => true,
        ]);

        VlessExternalSubscription::query()->create([
            'name' => 'Inactive WL',
            'type' => VlessExternalSubscription::TYPE_DIRECT,
            'source_url' => 'vless://uuid@inactive.example.com:443?type=tcp&security=reality#inactive',
            'is_active' => false,
            'is_ready' => true,
        ]);

        $this->artisan(SyncVlessExternalSubscriptionsCommand::class)
            ->assertSuccessful();

        Queue::assertPushed(SyncVlessExternalSubscriptionJob::class, function (SyncVlessExternalSubscriptionJob $job) use ($active) {
            return $job->subscriptionId === $active->id
                && $job->queue === null;
        });

        Queue::assertPushed(SyncVlessExternalSubscriptionJob::class, 1);
    }

    public function test_command_is_scheduled_every_fifteen_minutes(): void
    {
        $events = collect(Schedule::events());

        $event = $events->first(function ($scheduledEvent) {
            return str_contains((string) $scheduledEvent->command, 'vless-external-subscriptions:sync');
        });

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }

    public function test_manual_sync_queues_job_on_default_queue(): void
    {
        Queue::fake();

        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'is_admin' => true,
        ]);

        $subscription = VlessExternalSubscription::query()->create([
            'name' => 'Manual WL',
            'type' => VlessExternalSubscription::TYPE_DIRECT,
            'source_url' => 'vless://uuid@manual.example.com:443?type=tcp&security=reality#manual',
            'is_active' => true,
            'is_ready' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('vless-external-subscriptions.sync', $subscription))
            ->assertRedirect()
            ->assertSessionHas('success', 'Синхронизация поставлена в очередь.');

        Queue::assertPushed(SyncVlessExternalSubscriptionJob::class, function (SyncVlessExternalSubscriptionJob $job) use ($subscription) {
            return $job->subscriptionId === $subscription->id
                && $job->queue === null;
        });
    }
}
