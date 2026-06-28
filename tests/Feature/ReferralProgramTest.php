<?php

namespace Tests\Feature;

use App\Enums\ReferralRewardStatus;
use App\Models\CurrentPayment;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralRewardService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReferralProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-28 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_purchase_with_accumulated_referral_discount_resets_it_after_activation(): void
    {
        Queue::fake();

        CurrentPayment::query()->create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'amount' => 150,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123',
            'balance' => 1000,
            'referral_accumulated_discount_percent' => 15,
        ]);

        $quote = app(SubscriptionService::class)->buildPurchaseQuote($user, 1);

        $this->assertSame(127.5, $quote['package_price']);

        app(SubscriptionService::class)->activatePackageForUser(
            user: $user,
            months: 1,
            packagePrice: $quote['package_price'],
            purchaseMeta: [
                'subscription_months' => 1,
                'referral_accumulated_discount_percent' => $quote['referral_accumulated_discount_percent'],
                'referral_permanent_discount_percent' => $quote['referral_permanent_discount_percent'],
                'referral_total_discount_percent' => $quote['referral_total_discount_percent'],
                'referral_discount_amount' => $quote['referral_discount_amount'],
            ],
        );

        $this->assertSame(0, $user->fresh()->referral_accumulated_discount_percent);
    }

    public function test_first_referred_purchase_schedules_and_applies_reward_once(): void
    {
        Queue::fake();

        CurrentPayment::query()->create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'amount' => 150,
        ]);

        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@referrer',
            'telegram_id' => '111',
        ]);

        $invitee = User::query()->create([
            'name' => 'Invitee',
            'telegram' => '@invitee',
            'telegram_id' => '222',
            'balance' => 1000,
            'referrer_id' => $referrer->id,
        ]);

        $referral = Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referral_user_id' => $invitee->id,
        ]);

        $quote = app(SubscriptionService::class)->buildPurchaseQuote($invitee, 3);

        app(SubscriptionService::class)->activatePackageForUser(
            user: $invitee,
            months: 3,
            packagePrice: $quote['package_price'],
            purchaseMeta: [
                'subscription_months' => 3,
            ],
        );

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::WaitingConfirmation, $referral->reward_status);
        $this->assertNotNull($referral->qualifying_transaction_id);
        $this->assertSame(7, $referral->invitee_bonus_days);
        $this->assertSame(10, $referral->referrer_reward_percent);

        Carbon::setTestNow('2026-06-29 12:01:00');

        app(ReferralRewardService::class)->processReward($referral);

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::Rewarded, $referral->reward_status);
        $this->assertNotNull($referral->rewarded_at);
        $this->assertSame(10, $referrer->fresh()->referral_accumulated_discount_percent);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $invitee->id,
            'source' => 'referral_bonus',
            'price' => 0,
        ]);
    }
}
