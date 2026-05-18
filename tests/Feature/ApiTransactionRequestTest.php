<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTransactionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_request_requires_bank_and_positive_amount(): void
    {
        User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $this->postJson('/api/users/123456789/transactions', [
            'amount' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'amount',
                'bank',
            ]);
    }
}
