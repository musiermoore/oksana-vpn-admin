<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_register_endpoint_allows_existing_user_to_attach_referrer_once_from_start_param(): void
    {
        $referrer = User::query()->create([
            'name' => 'Referrer',
            'telegram' => '@referrer',
            'telegram_id' => '555',
        ]);

        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'telegram_id' => '123456789',
        ]);

        $response = $this->postJson('/api/users/register', [
            'telegram' => 'alice',
            'telegram_id' => '123456789',
            'name' => 'Alice',
            'start_param' => 'ref_'.$referrer->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.telegram_id', '123456789');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'referrer_id' => $referrer->id,
        ]);
    }
}
