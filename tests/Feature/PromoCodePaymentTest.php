<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PromoCodeBenefit;
use App\Jobs\DispatchDefaultConfigsForUserJob;
use App\Jobs\ReconcileUserAccessStateJob;
use App\Models\PaymentPeriod;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\TelegramAppToken;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Payments\YooKassaPaymentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class PromoCodePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const PREVIEW = '/telegram-app/payments/promo-codes/preview';

    private const PURCHASE = '/telegram-app/payments/subscriptions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
        Queue::fake();

        PaymentPeriod::query()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'amount' => 150,
        ]);
    }

    public function test_preview_does_not_use_code_and_fixed_discount_can_cover_the_package(): void
    {
        $promo = $this->promo(PromoCodeBenefit::FixedDiscount, 200);
        $customer = $this->authenticateCustomer();
        $this->mock(YooKassaPaymentService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('createPayment'));

        $this->postJson(self::PREVIEW, ['month' => 1, 'promo_code' => ' welcome '])
            ->assertOk()->assertExactJson([
                'code' => 'WELCOME', 'benefit' => 'fixed_discount', 'value' => 200,
                'discount_amount' => 150, 'price' => 0, 'payable_now' => 0,
            ]);
        $this->assertDatabaseEmpty('promo_code_redemptions');

        $this->postJson(self::PURCHASE, ['month' => 1, 'promo_code' => 'Welcome'])
            ->assertOk()->assertJsonPath('status', 'activated');

        $purchase = $customer->subscriptions()->sole();
        $redemption = $promo->redemptions()->sole();
        $this->assertSame(0.0, $purchase->price);
        $this->assertSame($purchase->transaction_id, $redemption->subscription_transaction_id);
        $this->assertNotNull($redemption->redeemed_at);
        $this->assertSame(150.0, $redemption->discount_amount);

        $this->postJson(self::PURCHASE, ['month' => 1, 'promo_code' => 'WELCOME'])->assertUnprocessable();
        $this->authenticateCustomer();
        $this->postJson(self::PREVIEW, ['month' => 1, 'promo_code' => 'WELCOME'])->assertOk();
        Queue::assertPushed(DispatchDefaultConfigsForUserJob::class);
        Queue::assertPushed(ReconcileUserAccessStateJob::class);
    }

    public function test_deposit_can_cover_checkout_and_leaves_surplus_for_the_next_payment(): void
    {
        $this->promo(PromoCodeBenefit::BalanceDeposit, 175.25);
        $customer = $this->authenticateCustomer();
        $this->mock(YooKassaPaymentService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('createPayment'));

        $this->postJson(self::PREVIEW, ['month' => 1, 'promo_code' => 'WELCOME'])
            ->assertOk()->assertJsonPath('price', 150)->assertJsonPath('payable_now', 0);

        $this->postJson(self::PURCHASE, ['month' => 1, 'promo_code' => 'WELCOME'])->assertOk();

        $this->assertSame(25.25, $customer->fresh()->getStoredBalanceAmount());
        $this->assertDatabaseHas('transactions', ['user_id' => $customer->id, 'amount' => 175.25, 'is_approved' => true]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $customer->id, 'amount' => -150, 'is_approved' => true, 'current_balance_amount' => 25.25,
        ]);
    }

    public function test_full_discount_activates_the_package_even_when_existing_debt_requires_payment(): void
    {
        $promo = $this->promo(PromoCodeBenefit::PercentageDiscount, 100);
        $customer = $this->authenticateCustomer();
        $customer->transactions()->create([
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
            'amount' => -10,
            'is_approved' => true,
        ]);
        $this->mock(YooKassaPaymentService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('createPayment')->once()->with(10.0, \Mockery::any(), \Mockery::any(), null)
            ->andReturn($this->providerPayment('debt', 10)));

        $this->postJson(self::PURCHASE, ['month' => 1, 'promo_code' => 'WELCOME'])
            ->assertOk()->assertJsonPath('deposit_amount', 10);
        $this->webhook('debt', 'succeeded', 10);

        $this->assertSame(0.0, $customer->fresh()->getStoredBalanceAmount());
        $this->assertSame(0.0, $customer->subscriptions()->sole()->price);
        $this->assertNotNull($promo->redemptions()->sole()->redeemed_at);
    }

    public function test_bonus_days_extend_the_purchased_subscription_after_the_trial(): void
    {
        $this->promo(PromoCodeBenefit::BonusDays, 5, [
            'starts_on' => '2026-11-19', 'ends_on' => '2026-11-19', 'new_users_only' => true,
        ]);

        $customer = $this->authenticateCustomer();
        $customer->transactions()->create([
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 150,
            'is_approved' => true,
        ]);
        $this->postJson(self::PURCHASE, ['month' => 0])->assertOk();
        $this->postJson(self::PURCHASE, ['month' => 1, 'promo_code' => 'WELCOME'])
            ->assertOk()->assertJsonPath('end_date', '2026-12-27');

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $customer->id, 'start_date' => '2026-11-22', 'end_date' => '2026-12-27', 'source' => 'purchase',
        ]);
        $this->postJson(self::PREVIEW, ['month' => 1, 'promo_code' => 'WELCOME'])
            ->assertUnprocessable()->assertJsonPath('message', 'Промокод доступен только новым пользователям.');
    }

    public function test_purchase_gift_is_awarded_once_per_payment_and_survives_archiving(): void
    {
        $promo = $this->promo(PromoCodeBenefit::BalanceCashback, 25, ['once_per_user' => false]);
        $customer = $this->authenticateCustomer();
        $this->mock(YooKassaPaymentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createPayment')->once()->with(150.0, \Mockery::any(), \Mockery::any(), null)
                ->andReturn($this->providerPayment('first', 150));
            $mock->shouldReceive('createPayment')->once()->with(125.0, \Mockery::any(), \Mockery::any(), null)
                ->andReturn($this->providerPayment('second', 125));
        });

        $payload = ['month' => 1, 'promo_code' => 'WELCOME'];
        $this->postJson(self::PREVIEW, $payload)->assertOk()->assertJsonPath('payable_now', 150);
        $this->postJson(self::PURCHASE, $payload)->assertOk()->assertJsonPath('payment_id', 'first');

        $this->assertSame(0.0, $customer->fresh()->getStoredBalanceAmount());
        $this->assertNull($promo->redemptions()->sole()->redeemed_at);

        $this->webhook('first', 'succeeded', 150);
        $this->webhook('first', 'succeeded', 150);
        $this->assertSame(25.0, $customer->fresh()->getStoredBalanceAmount());
        $this->assertDatabaseCount('user_subscriptions', 1);

        $this->postJson(self::PURCHASE, $payload)->assertOk()->assertJsonPath('deposit_amount', 125);
        $promo->update(['value' => 100, 'is_active' => false]);
        $promo->delete();

        $this->webhook('second', 'succeeded', 125);
        $this->assertSame(25.0, $customer->fresh()->getStoredBalanceAmount());
        $this->assertDatabaseCount('user_subscriptions', 2);
        $this->assertSame(2, PromoCodeRedemption::query()->whereNotNull('redeemed_at')->where('value', 25)->count());
        $this->postJson(self::PREVIEW, $payload)->assertUnprocessable();
    }

    public function test_canceled_payment_releases_the_reservation_and_partial_deposit_is_applied_at_settlement(): void
    {
        $promo = $this->promo(PromoCodeBenefit::BalanceDeposit, 20, ['new_users_only' => true]);
        $this->promo(PromoCodeBenefit::FixedDiscount, 20, ['code' => 'ANOTHER', 'new_users_only' => true]);
        $customer = $this->authenticateCustomer();
        $this->mock(YooKassaPaymentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createPayment')->twice()->with(130.0, \Mockery::any(), \Mockery::any(), null)
                ->andReturn($this->providerPayment('canceled', 130), $this->providerPayment('completed', 130));
        });

        $payload = ['month' => 1, 'promo_code' => 'WELCOME'];
        $this->postJson(self::PURCHASE, $payload)->assertOk()->assertJsonPath('deposit_amount', 130);
        $this->postJson(self::PURCHASE, $payload)->assertUnprocessable();
        $this->postJson(self::PURCHASE, ['month' => 1, 'promo_code' => 'ANOTHER'])->assertUnprocessable();

        $this->webhook('canceled', 'canceled', 130);
        $this->assertNotNull($promo->redemptions()->sole()->canceled_at);
        $this->assertSame(0.0, $customer->fresh()->getStoredBalanceAmount());
        $this->assertDatabaseEmpty('user_subscriptions');

        $this->postJson(self::PURCHASE, $payload)->assertOk()->assertJsonPath('payment_id', 'completed');
        $this->webhook('completed', 'succeeded', 130);

        $this->assertSame(0.0, $customer->fresh()->getStoredBalanceAmount());
        $this->assertNotNull($promo->redemptions()->whereNotNull('redeemed_at')->sole()->subscription_transaction_id);
        $this->postJson(self::PURCHASE, $payload)->assertUnprocessable();
    }

    public function test_provider_initialization_failure_releases_the_code(): void
    {
        $promo = $this->promo(PromoCodeBenefit::PercentageDiscount, 10);
        $this->authenticateCustomer();
        $this->mock(YooKassaPaymentService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('createPayment')->once()->with(135.0, \Mockery::any(), \Mockery::any(), null)
            ->andThrow(new DomainException('Provider unavailable.')));

        $payload = ['month' => 1, 'promo_code' => 'WELCOME'];
        $this->postJson(self::PURCHASE, $payload)->assertUnprocessable();
        $this->assertNotNull($promo->redemptions()->sole()->canceled_at);
        $this->postJson(self::PREVIEW, $payload)->assertOk()->assertJsonPath('discount_amount', 15);
    }

    public function test_disabled_expired_and_upcoming_codes_cannot_be_used(): void
    {
        $promo = $this->promo(PromoCodeBenefit::FixedDiscount, 20, ['is_active' => false]);
        $this->authenticateCustomer();
        $payload = ['month' => 1, 'promo_code' => 'WELCOME'];

        $this->postJson(self::PURCHASE, $payload)->assertUnprocessable();
        $promo->update(['is_active' => true, 'ends_on' => '2026-11-18']);
        $this->postJson(self::PURCHASE, $payload)->assertUnprocessable();
        $promo->update(['ends_on' => null, 'starts_on' => '2026-11-20']);
        $this->postJson(self::PURCHASE, $payload)->assertUnprocessable();
        $this->assertDatabaseEmpty('promo_code_redemptions');
    }

    public function test_purchase_rejects_codes_for_trial_and_gift_code_checkouts(): void
    {
        $this->promo(PromoCodeBenefit::BalanceCashback, 20);
        $this->authenticateCustomer();

        $this->postJson(self::PURCHASE, ['month' => 0, 'promo_code' => 'WELCOME'])->assertUnprocessable();
        $this->postJson(self::PURCHASE, ['month' => 1, 'purchase_type' => 'GIFT', 'promo_code' => 'WELCOME'])->assertUnprocessable();
        $this->assertDatabaseEmpty('promo_code_redemptions');
    }

    private function authenticateCustomer(): User
    {
        $user = User::factory()->create(['balance' => 0]);
        $token = str()->random(80);

        TelegramAppToken::query()->create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token)]);
        $this->withToken($token);

        return $user;
    }

    private function promo(PromoCodeBenefit $benefit, float $value, array $attributes = []): PromoCode
    {
        return PromoCode::query()->create([
            'code' => 'WELCOME',
            'benefit' => $benefit,
            'value' => $value,
            ...$attributes,
        ]);
    }

    private function providerPayment(string $id, int $amount): array
    {
        return [
            'id' => $id,
            'status' => 'pending',
            'paid' => false,
            'amount' => ['value' => $amount, 'currency' => 'RUB'],
            'confirmation' => ['confirmation_url' => 'https://yookassa.example/'.$id],
        ];
    }

    private function webhook(string $id, string $status, int $amount): void
    {
        $this->postJson('/api/payment/webhook', [
            'event' => 'payment.'.$status,
            'object' => [
                'id' => $id,
                'status' => $status,
                'paid' => $status === 'succeeded',
                'amount' => ['value' => $amount, 'currency' => 'RUB'],
            ],
        ])->assertOk();
    }
}
