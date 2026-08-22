<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReferralRewardStatus;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackfillReferralRewardsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-22 12:00:00');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_backfills_old_pending_referral_reward_from_approved_purchase(): void
    {
        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@referrer',
            'telegram_id' => '111',
        ]);

        $invitee = User::query()->create([
            'name' => 'Invitee',
            'telegram' => '@invitee',
            'telegram_id' => '222',
            'referrer_id' => $referrer->id,
        ]);

        $referral = Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referral_user_id' => $invitee->id,
            'created_at' => '2026-06-28 15:03:56',
            'updated_at' => '2026-06-28 15:03:56',
        ]);

        $qualifyingTransaction = Transaction::query()->create([
            'user_id' => $invitee->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 150,
            'is_approved' => true,
            'description' => 'YooKassa',
            'extra_data' => [
                'subscription_months' => 1,
                'package_price' => 150,
            ],
            'created_at' => '2026-06-29 10:00:00',
            'updated_at' => '2026-06-29 10:00:00',
        ]);

        $this->artisan('referrals:backfill-rewards')
            ->assertSuccessful()
            ->expectsOutput('Referral reward backfill completed.')
            ->expectsOutput('Processed: 1')
            ->expectsOutput('Rewarded: 1')
            ->expectsOutput('Scheduled: 0')
            ->expectsOutput('Pending without purchase: 0')
            ->expectsOutput('Skipped missing user: 0');

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::Rewarded, $referral->reward_status);
        $this->assertSame($qualifyingTransaction->id, $referral->qualifying_transaction_id);
        $this->assertSame(3, $referral->invitee_bonus_days);
        $this->assertSame(5, $referral->referrer_reward_percent);
        $this->assertNotNull($referral->reward_scheduled_at);
        $this->assertNotNull($referral->rewarded_at);
        $this->assertSame(5, $referrer->fresh()->referral_accumulated_discount_percent);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $invitee->id,
            'source' => 'referral_bonus',
            'price' => 0,
            'transaction_id' => $qualifyingTransaction->id,
        ]);
    }

    public function test_command_keeps_pending_referral_without_approved_purchase(): void
    {
        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@referrer2',
            'telegram_id' => '333',
        ]);

        $invitee = User::query()->create([
            'name' => 'Invitee',
            'telegram' => '@invitee2',
            'telegram_id' => '444',
            'referrer_id' => $referrer->id,
        ]);

        $referral = Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referral_user_id' => $invitee->id,
        ]);

        $this->artisan('referrals:backfill-rewards', [
            'referral_id' => $referral->id,
        ])
            ->assertSuccessful()
            ->expectsOutput('Referral reward backfill completed.')
            ->expectsOutput('Processed: 1')
            ->expectsOutput('Rewarded: 0')
            ->expectsOutput('Scheduled: 0')
            ->expectsOutput('Pending without purchase: 1')
            ->expectsOutput('Skipped missing user: 0');

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::Pending, $referral->reward_status);
        $this->assertNull($referral->qualifying_transaction_id);
        $this->assertSame(0, $referrer->fresh()->referral_accumulated_discount_percent);
    }
}
