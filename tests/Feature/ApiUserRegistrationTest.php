<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiUserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_endpoint_creates_user_with_telegram_id(): void
    {
        $response = $this->postJson('/api/users/register', [
            'telegram' => 'alice',
            'telegram_id' => '123456789',
            'name' => 'Alice',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.telegram', '@alice')
            ->assertJsonPath('user.telegram_id', '123456789');

        $this->assertDatabaseHas('users', [
            'telegram' => '@alice',
            'telegram_id' => '123456789',
            'name' => 'Alice',
        ]);
    }

    public function test_register_endpoint_creates_user_with_only_telegram_id(): void
    {
        $response = $this->postJson('/api/users/register', [
            'telegram_id' => '987654321',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.telegram', null)
            ->assertJsonPath('user.telegram_id', '987654321')
            ->assertJsonPath('user.name', '987654321');

        $this->assertDatabaseHas('users', [
            'telegram' => null,
            'telegram_id' => '987654321',
            'name' => '987654321',
        ]);
    }
}
