<?php

namespace Tests\Feature;

use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

class ApiTransactionRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_transactions_route_creates_pending_deposit_request(): void
    {
        $telegram = Mockery::mock();
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['chat_id'] === '777777'
                    && str_contains($payload['text'], 'пополнил баланс на 250')
                    && str_contains($payload['text'], '(T-Bank)');
            })
            ->andReturnTrue();
        Telegram::swap($telegram);

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
            'balance' => 0,
        ]);

        $this->postJson("/api/users/{$user->telegram_id}/transactions", [
            'amount' => 250,
            'bank' => 'T-Bank',
        ])->assertOk()
            ->assertExactJson([
                'message' => 'Запрос на пополнение 250 (T-Bank) отправлен.',
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 250,
            'description' => 'T-Bank',
            'is_approved' => false,
        ]);
    }
}
