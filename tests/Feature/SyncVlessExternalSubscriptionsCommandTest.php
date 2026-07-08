<?php

namespace Tests\Feature;

use App\Console\Commands\SyncVlessExternalSubscriptionsCommand;
use App\Jobs\SyncVlessExternalSubscriptionJob;
use App\Models\VlessExternalSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SyncVlessExternalSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_jobs_only_for_active_subscriptions(): void
    {
        Bus::fake();

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

        Bus::assertDispatched(SyncVlessExternalSubscriptionJob::class, function (SyncVlessExternalSubscriptionJob $job) use ($active) {
            return $job->subscriptionId === $active->id;
        });

        Bus::assertDispatchedTimes(SyncVlessExternalSubscriptionJob::class, 1);
    }
}
