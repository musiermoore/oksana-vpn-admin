<?php

namespace Tests\Feature;

use App\Models\CurrentPayment;
use App\Models\Invoice;
use App\Models\SubscriptionCode;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Payments\YooKassaPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

class ApiTransactionRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-18 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_transactions_route_creates_pending_deposit_request(): void
    {
        $paymentService = Mockery::mock(YooKassaPaymentService::class);
        $paymentService->shouldReceive('createPayment')
            ->once()
            ->withArgs(function (float $amount, string $description, array $metadata, ?string $returnUrl): bool {
                return $amount === 520.0
                    && $description === 'Подписка 6 мес. для @alice'
                    && $metadata['subscription_months'] === '6'
                    && $returnUrl === 'https://app.example/return';
            })
            ->andReturn([
                'id' => '23d93cac-000f-5000-8000-126628f15141',
                'status' => 'pending',
                'paid' => false,
                'amount' => [
                    'value' => '520.00',
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'confirmation_url' => 'https://yookassa.example/confirm',
                ],
                'description' => 'Заказ №1',
                'raw' => [
                    'id' => '23d93cac-000f-5000-8000-126628f15141',
                    'status' => 'pending',
                ],
            ]);
        $this->app->instance(YooKassaPaymentService::class, $paymentService);

        $telegram = Mockery::mock();
        $telegram->shouldNotReceive('sendMessage');
        Telegram::swap($telegram);

        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 150,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'join_at' => '2026-05-01',
            'balance' => 200,
        ]);

        $this->postJson("/api/users/{$user->telegram_id}/transactions", [
            'month' => 6,
            'return_url' => 'https://app.example/return',
        ])->assertOk()
            ->assertExactJson([
                'status' => 'deposit_required',
                'message' => 'Для активации подписки необходимо оплатить 520 ₽. Чтобы перейти к оплате нажмите на кнопку «Перейти к оплате картой / СБП».',
                'deposit_amount' => 520.0,
                'transaction_id' => 1,
                'invoice_id' => 1,
                'payment_id' => '23d93cac-000f-5000-8000-126628f15141',
                'payment_status' => 'pending',
                'confirmation_url' => 'https://yookassa.example/confirm',
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'invoice_id' => 1,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'description' => 'YooKassa',
            'is_approved' => false,
        ]);

        $transaction = Transaction::query()->sole();
        $invoice = Invoice::query()->sole();

        $this->assertSame($invoice->id, $transaction->invoice_id);
        $this->assertSame('23d93cac-000f-5000-8000-126628f15141', $invoice->provider_payment_id);
        $this->assertSame('https://yookassa.example/confirm', $invoice->confirmation_url);

        $this->assertSame([
            'subscription_months' => 6,
            'base_month_price' => 150,
            'discount_percent' => 20,
            'package_full_price' => 900,
            'price_before_referral_discount' => 720,
            'package_price' => 720,
            'balance_before' => 200,
            'deposit_amount' => 520,
            'referral_accumulated_discount_percent' => 0,
            'referral_permanent_discount_percent' => 0,
            'referral_total_discount_percent' => 0,
            'referral_discount_amount' => 0,
        ], $transaction->extra_data);
    }

    public function test_transactions_route_activates_subscription_immediately_when_balance_is_enough(): void
    {
        $telegram = Mockery::mock();
        $telegram->shouldNotReceive('sendMessage');
        Telegram::swap($telegram);

        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 150,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'join_at' => '2026-05-01',
            'balance' => 1000,
        ]);

        $this->postJson("/api/users/{$user->telegram_id}/transactions", [
            'month' => 6,
        ])->assertOk()
            ->assertExactJson([
                'status' => 'activated',
                'message' => 'Подписка активирована до 18.11.2026.',
                'end_date' => '2026-11-18',
                'formatted_end_date' => '18.11.2026',
            ]);

        $this->assertDatabaseMissing('transactions', [
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -720,
            'is_approved' => true,
            'description' => 'Покупка подписки на 6 мес.',
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'start_date' => '2026-05-18',
            'end_date' => '2026-11-18',
            'price' => 720,
        ]);
    }

    public function test_transactions_route_creates_gift_code_when_balance_is_enough(): void
    {
        $telegram = Mockery::mock();
        $telegram->shouldNotReceive('sendMessage');
        Telegram::swap($telegram);

        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 150,
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'join_at' => '2026-05-01',
            'balance' => 1000,
        ]);

        $this->postJson("/api/users/{$user->telegram_id}/transactions", [
            'month' => 6,
            'purchase_type' => 'GIFT',
        ])->assertOk()
            ->assertJsonPath('status', 'gift_code_created')
            ->assertJsonPath('message', 'Подарочный код создан. Передайте его получателю для активации в mini-app.')
            ->assertJsonPath('months', 6)
            ->assertJsonPath('days', 180);

        $code = SubscriptionCode::query()->sole();

        $this->assertSame($user->id, $code->buyer_user_id);
        $this->assertSame(6, $code->months);
        $this->assertSame(180, $code->days);
        $this->assertSame(720.0, $code->price);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => -720,
            'is_approved' => true,
            'description' => 'Подарочный код на 6 мес.',
        ]);

        $this->assertDatabaseCount('user_subscriptions', 0);
    }

    public function test_transactions_route_activates_trial_subscription_without_yookassa(): void
    {
        $paymentService = Mockery::mock(YooKassaPaymentService::class);
        $paymentService->shouldNotReceive('createPayment');
        $this->app->instance(YooKassaPaymentService::class, $paymentService);

        $telegram = Mockery::mock();
        $telegram->shouldNotReceive('sendMessage');
        Telegram::swap($telegram);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'join_at' => '2026-05-01',
            'balance' => 0,
        ]);

        $this->postJson("/api/users/{$user->telegram_id}/transactions", [
            'month' => 0,
        ])->assertOk()
            ->assertExactJson([
                'status' => 'activated',
                'message' => 'Пробная подписка активирована до 20.05.2026.',
                'end_date' => '2026-05-20',
                'formatted_end_date' => '20.05.2026',
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 0,
            'is_approved' => true,
            'description' => 'Пробная подписка на 2 дня',
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'start_date' => '2026-05-18',
            'end_date' => '2026-05-20',
            'price' => 0,
            'source' => 'trial',
        ]);
    }

    public function test_transactions_route_rejects_trial_when_user_had_subscriptions_before(): void
    {
        $telegram = Mockery::mock();
        $telegram->shouldNotReceive('sendMessage');
        Telegram::swap($telegram);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'join_at' => '2026-05-01',
            'balance' => 0,
        ]);

        $user->subscriptions()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-10',
            'price' => 150,
            'source' => 'purchase',
        ]);

        $this->postJson("/api/users/{$user->telegram_id}/transactions", [
            'month' => 0,
        ])->assertStatus(422)
            ->assertExactJson([
                'message' => 'Пробная подписка больше недоступна для этого аккаунта.',
            ]);
    }
}
