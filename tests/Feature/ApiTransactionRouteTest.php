<?php

namespace Tests\Feature;

use App\Models\CurrentPayment;
use App\Models\TransactionType;
use App\Models\User;
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
        $telegram = Mockery::mock();
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['chat_id'] === '777777'
                    && str_contains($payload['text'], 'запросил пополнение на 520')
                    && str_contains($payload['text'], '(T-Bank)')
                    && str_contains($payload['text'], '6 мес.')
                    && str_contains($payload['text'], 'Сумма подписки: 720')
                    && str_contains($payload['text'], 'скидка: 20%');
            })
            ->andReturnTrue();
        Telegram::swap($telegram);

        CurrentPayment::query()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'amount' => 150,
        ]);

        User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'telegram_id' => '777777',
            'is_admin' => true,
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
            'bank' => 'T-Bank',
        ])->assertOk()
            ->assertExactJson([
                'status' => 'deposit_required',
                'message' => 'Для активации подписки нужно пополнить баланс на 520.',
                'deposit_amount' => 520.0,
                'transaction_id' => 1,
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'description' => 'T-Bank',
            'is_approved' => false,
        ]);

        $transaction = \App\Models\Transaction::query()->sole();

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
            'bank' => 'T-Bank',
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
