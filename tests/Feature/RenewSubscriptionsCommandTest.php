<?php

namespace Tests\Feature;

use App\Models\CurrentPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-10 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_renews_expired_subscription_when_balance_is_enough(): void
    {
        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 100,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => '2026-01-01',
            'balance' => 150,
        ]);

        UserSubscription::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'price' => 100,
        ]);

        $this->artisan('subscriptions:renew')
            ->assertSuccessful();

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'start_date' => '2026-05-10',
            'end_date' => '2026-06-10',
            'price' => 100,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -100,
            'is_approved' => true,
            'description' => 'Продление подписки',
        ]);

        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->count());
    }

    public function test_command_creates_first_subscription_when_user_has_enough_balance(): void
    {
        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 100,
        ]);

        $user = User::query()->create([
            'name' => 'Bob',
            'telegram' => '@bob',
            'join_at' => '2026-05-10',
            'balance' => 120,
        ]);

        $this->artisan('subscriptions:renew')
            ->assertSuccessful();

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'start_date' => '2026-05-10',
            'end_date' => '2026-06-10',
            'price' => 100,
        ]);

        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->count());
    }

    public function test_command_does_not_create_subscription_when_balance_is_not_enough(): void
    {
        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 100,
        ]);

        $user = User::query()->create([
            'name' => 'Charlie',
            'telegram' => '@charlie',
            'join_at' => '2026-05-10',
            'balance' => 99,
        ]);

        $this->artisan('subscriptions:renew')
            ->assertSuccessful();

        $this->assertDatabaseMissing('user_subscriptions', [
            'user_id' => $user->id,
        ]);

        $this->assertSame(99.0, $user->fresh()->balance);
        $this->assertSame(0, Transaction::query()->where('user_id', $user->id)->count());
    }
}
