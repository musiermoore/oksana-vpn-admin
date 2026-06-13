<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Support\BotApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTransactionTelegramMessageRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_message_route_saves_message_metadata_for_user_transaction(): void
    {
        $user = $this->createUser('123456789', '@alice');
        $transaction = $this->createTransactionForUser($user);

        $this->patchJson("/api/users/{$user->telegram_id}/transactions/{$transaction->id}/telegram-message", [
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
        ])->assertNoContent();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $user->id,
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
        ]);
    }

    public function test_telegram_message_route_validates_payload(): void
    {
        $user = $this->createUser('123456789', '@alice');
        $transaction = $this->createTransactionForUser($user);

        $this->patchJson("/api/users/{$user->telegram_id}/transactions/{$transaction->id}/telegram-message", [
            'telegram_chat_id' => 'oops',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'telegram_chat_id',
                'telegram_message_id',
            ]);
    }

    public function test_telegram_message_route_returns_not_found_for_transaction_of_another_user(): void
    {
        $user = $this->createUser('123456789', '@alice');
        $anotherUser = $this->createUser('987654321', '@bob');
        $transaction = $this->createTransactionForUser($anotherUser);

        $this->patchJson("/api/users/{$user->telegram_id}/transactions/{$transaction->id}/telegram-message", [
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'Transaction not found.',
            ]);
    }

    public function test_telegram_message_route_returns_not_found_for_missing_transaction(): void
    {
        $user = $this->createUser('123456789', '@alice');

        $this->patchJson("/api/users/{$user->telegram_id}/transactions/999999/telegram-message", [
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
        ])->assertNotFound()
            ->assertExactJson([
                'message' => 'Transaction not found.',
            ]);
    }

    public function test_telegram_message_route_returns_not_found_for_unknown_telegram_id(): void
    {
        $this->patchJson('/api/users/missing-user/transactions/1/telegram-message', [
            'telegram_chat_id' => 777,
            'telegram_message_id' => 999,
        ])->assertNotFound()
            ->assertExactJson([
                'message' => BotApiMessages::userNotFound(),
            ]);
    }

    private function createUser(string $telegramId, string $telegram): User
    {
        return User::query()->create([
            'name' => ltrim($telegram, '@'),
            'telegram' => $telegram,
            'telegram_id' => $telegramId,
            'join_at' => '2026-06-01',
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    private function createTransactionForUser(User $user): Transaction
    {
        return Transaction::query()->create([
            'user_id' => $user->id,
            'type_id' => TransactionType::idBySlug(TransactionType::SLUG_DEPOSIT),
            'amount' => 520,
            'is_approved' => false,
            'description' => 'YooKassa',
        ]);
    }
}
