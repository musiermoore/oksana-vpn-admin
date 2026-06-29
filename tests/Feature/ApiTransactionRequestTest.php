<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTransactionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_request_requires_supported_month_package(): void
    {
        User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $this->postJson('/api/users/123456789/transactions', [
            'month' => 2,
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'month',
            ]);
    }

    public function test_transaction_request_validates_return_url_when_it_is_present(): void
    {
        User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $this->postJson('/api/users/123456789/transactions', [
            'month' => 1,
            'return_url' => 'not-a-url',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'return_url',
            ]);
    }

    public function test_transaction_request_requires_supported_purchase_type(): void
    {
        User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'balance' => 0,
        ]);

        $this->postJson('/api/users/123456789/transactions', [
            'month' => 1,
            'purchase_type' => 'coupon',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'purchase_type',
            ]);
    }
}
