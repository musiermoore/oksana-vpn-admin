<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReferralRewardStatus;
use App\Jobs\ProcessReferralRewardJob;
use App\Models\Referral;
use App\Models\SubscriptionCode;
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
            ->expectsOutput('Referral reward backfill completed.');

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::WaitingConfirmation, $referral->reward_status);
        $this->assertSame($qualifyingTransaction->id, $referral->qualifying_transaction_id);
        $this->assertSame(3, $referral->invitee_bonus_days);
        $this->assertSame(5, $referral->referrer_reward_percent);
        $this->assertNotNull($referral->reward_scheduled_at);
        $this->assertNull($referral->rewarded_at);
        $this->assertSame(0, $referrer->fresh()->referral_accumulated_discount_percent);

        Queue::assertPushed(ProcessReferralRewardJob::class, function (ProcessReferralRewardJob $job) use ($referral): bool {
            return $job->referralId === $referral->id;
        });
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
            ->expectsOutput('Referral reward backfill completed.');

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::Pending, $referral->reward_status);
        $this->assertNull($referral->qualifying_transaction_id);
        $this->assertSame(0, $referrer->fresh()->referral_accumulated_discount_percent);
    }

    public function test_command_backfills_pending_referral_reward_from_activated_subscription_code(): void
    {
        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@ref-code',
            'telegram_id' => '555',
        ]);

        $buyer = User::query()->create([
            'name' => 'Buyer',
            'telegram' => '@buyer-code',
            'telegram_id' => '666',
        ]);

        $invitee = User::query()->create([
            'name' => 'Invitee',
            'telegram' => '@invitee-code',
            'telegram_id' => '777',
            'referrer_id' => $referrer->id,
        ]);

        $referral = Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referral_user_id' => $invitee->id,
            'created_at' => '2026-07-03 17:03:08',
            'updated_at' => '2026-07-03 17:03:08',
        ]);

        $giftTransaction = Transaction::query()->create([
            'user_id' => $buyer->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
            'amount' => -1260,
            'is_approved' => true,
            'description' => 'Подарочный код на 12 мес.',
            'extra_data' => [
                'purchase_type' => 'GIFT',
                'subscription_months' => 12,
                'package_price' => 1260,
            ],
            'created_at' => '2026-07-03 17:04:00',
            'updated_at' => '2026-07-03 17:04:00',
        ]);

        SubscriptionCode::query()->create([
            'buyer_user_id' => $buyer->id,
            'activated_by_user_id' => $invitee->id,
            'transaction_id' => $giftTransaction->id,
            'code' => 'SUBSCODE1234',
            'months' => 12,
            'days' => 365,
            'price' => 1260,
            'status' => SubscriptionCode::STATUS_ACTIVATED,
            'activated_at' => '2026-07-03 17:05:37',
            'meta' => [
                'subscription_months' => 12,
                'purchase_type' => 'GIFT',
            ],
        ]);

        $this->artisan('referrals:backfill-rewards', [
            'referral_id' => $referral->id,
        ])
            ->assertSuccessful()
            ->expectsOutput('Referral reward backfill completed.');

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::Rewarded, $referral->reward_status);
        $this->assertSame($giftTransaction->id, $referral->qualifying_transaction_id);
        $this->assertSame(30, $referral->invitee_bonus_days);
        $this->assertSame(25, $referral->referrer_reward_percent);
        $this->assertNotNull($referral->rewarded_at);
        $this->assertSame(25, $referrer->fresh()->referral_accumulated_discount_percent);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $invitee->id,
            'source' => 'referral_bonus',
            'price' => 0,
            'transaction_id' => $giftTransaction->id,
        ]);
    }
}
