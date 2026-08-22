<?php

namespace Tests\Feature;

use App\Enums\ReferralRewardStatus;
use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Jobs\ProcessReferralRewardJob;
use App\Models\Referral;
use App\Models\SubscriptionCode;
use App\Models\TelegramAppToken;
use App\Models\User;
use Carbon\Carbon;
use App\Services\ReferralRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramAppSubscriptionCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-29 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_telegram_app_user_can_activate_gift_subscription_code(): void
    {
        Queue::fake();

        $buyer = User::factory()->create([
            'telegram' => '@buyer',
            'telegram_id' => '111',
        ]);

        $recipient = User::factory()->create([
            'telegram' => '@recipient',
            'telegram_id' => '222',
        ]);

        $plainTextToken = str_repeat('b', 80);

        TelegramAppToken::query()->create([
            'user_id' => $recipient->id,
            'token_hash' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        $code = SubscriptionCode::query()->create([
            'buyer_user_id' => $buyer->id,
            'code' => 'ABCD1234EFGH',
            'months' => 3,
            'days' => 90,
            'price' => 405,
            'status' => SubscriptionCode::STATUS_ACTIVE,
        ]);

        $this->withToken($plainTextToken)
            ->postJson('/telegram-app/payments/subscription-codes/activate', [
                'code' => 'ABCD 1234 EFGH',
            ])->assertOk()
            ->assertExactJson([
                'status' => 'activated',
                'message' => 'Код активирован. Подписка уже применена к вашему аккаунту.',
                'code' => 'ABCD1234EFGH',
            ]);

        $this->assertDatabaseHas('subscription_codes', [
            'id' => $code->id,
            'activated_by_user_id' => $recipient->id,
            'status' => SubscriptionCode::STATUS_ACTIVATED,
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $recipient->id,
            'start_date' => '2026-06-29',
            'end_date' => '2026-09-29',
            'price' => 405,
            'source' => 'gift_code',
        ]);

        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class);
    }

    public function test_telegram_app_subscription_code_activation_schedules_referral_reward_for_invited_user(): void
    {
        Queue::fake();

        $referrer = User::factory()->create([
            'telegram' => '@referrer',
            'telegram_id' => '101',
        ]);

        $buyer = User::factory()->create([
            'telegram' => '@buyer',
            'telegram_id' => '111',
        ]);

        $recipient = User::factory()->create([
            'telegram' => '@recipient',
            'telegram_id' => '222',
            'referrer_id' => $referrer->id,
        ]);

        $referral = Referral::query()->create([
            'referrer_id' => $referrer->id,
            'referral_user_id' => $recipient->id,
        ]);

        $plainTextToken = str_repeat('c', 80);

        TelegramAppToken::query()->create([
            'user_id' => $recipient->id,
            'token_hash' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        $code = SubscriptionCode::query()->create([
            'buyer_user_id' => $buyer->id,
            'transaction_id' => $buyer->transactions()->create([
                'type_id' => \App\Models\TransactionType::idBySlug(\App\Models\TransactionType::SLUG_SUBSCRIPTION),
                'amount' => -405,
                'is_approved' => true,
                'description' => 'Подарочный код на 3 мес.',
                'extra_data' => [
                    'purchase_type' => 'GIFT',
                    'subscription_months' => 3,
                    'package_price' => 405,
                ],
            ])->id,
            'code' => 'ZXCV1234BNMQ',
            'months' => 3,
            'days' => 90,
            'price' => 405,
            'status' => SubscriptionCode::STATUS_ACTIVE,
        ]);

        $this->withToken($plainTextToken)
            ->postJson('/telegram-app/payments/subscription-codes/activate', [
                'code' => 'ZXCV 1234 BNMQ',
            ])->assertOk();

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::WaitingConfirmation, $referral->reward_status);
        $this->assertSame($code->transaction_id, $referral->qualifying_transaction_id);
        $this->assertSame(7, $referral->invitee_bonus_days);
        $this->assertSame(10, $referral->referrer_reward_percent);

        Queue::assertPushed(ProcessReferralRewardJob::class, fn (ProcessReferralRewardJob $job): bool => $job->referralId === $referral->id);

        Carbon::setTestNow('2026-06-30 12:01:00');

        app(ReferralRewardService::class)->processReward($referral);

        $referral->refresh();

        $this->assertSame(ReferralRewardStatus::Rewarded, $referral->reward_status);
        $this->assertSame(10, $referrer->fresh()->referral_accumulated_discount_percent);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $recipient->id,
            'source' => 'referral_bonus',
            'price' => 0,
            'transaction_id' => $code->transaction_id,
        ]);
    }
}
