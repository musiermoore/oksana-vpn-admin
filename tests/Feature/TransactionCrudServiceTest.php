<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Crud\TransactionCrudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCrudServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_approved_deposit_updates_balance_once(): void
    {
        $user = $this->createUser(balance: 0);

        app(TransactionCrudService::class)->create(new \App\DTOs\Transaction\TransactionData(
            userId: $user->id,
            typeId: TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            amount: 100,
            description: 'Manual deposit',
            isApproved: true,
        ));

        $this->assertSame(100.0, $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 100,
            'current_balance_amount' => 100,
            'is_approved' => true,
        ]);
    }

    public function test_creating_a_subscription_from_positive_input_saves_negative_amount_and_updates_balance_once(): void
    {
        $user = $this->createUser(balance: 200);

        app(TransactionCrudService::class)->create(new \App\DTOs\Transaction\TransactionData(
            userId: $user->id,
            typeId: TransactionType::idBySlug(TransactionType::SLUG_SUBSCRIPTION),
            amount: 100,
            description: 'Manual subscription charge',
            isApproved: true,
        ));

        $transaction = Transaction::query()->sole();

        $this->assertSame(-100.0, (float) $transaction->amount);
        $this->assertSame(100.0, $user->fresh()->balance);
        $this->assertSame(-100.0, (float) $transaction->current_balance_amount);
    }

    public function test_negative_transactions_use_charge_wording(): void
    {
        $user = $this->createUser(balance: 50, telegramId: '123456');
        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_EXTRA_PAYMENT),
            'amount' => -25,
            'is_approved' => false,
            'description' => 'Extra payment',
        ]);

        $this->assertSame('С баланса было списано 25', $transaction->approval_message_text);
    }

    private function createUser(float $balance, string $telegramId = '100200'): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'telegram' => '@tester',
            'telegram_id' => $telegramId,
            'join_at' => '2026-05-01',
            'balance' => $balance,
            'password' => bcrypt('password'),
        ]);
    }
}
