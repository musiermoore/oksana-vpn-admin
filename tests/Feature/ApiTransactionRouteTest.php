<?php

namespace Tests\Feature;

use App\Models\CurrentPayment;
use App\Models\Invoice;
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
                'message' => 'Для активации подписки нужно оплатить 520 через YooKassa.',
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
            'package_price' => 720,
            'balance_before' => 200,
            'deposit_amount' => 520,
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
}
