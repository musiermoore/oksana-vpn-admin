<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSaveTelegramIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_id_endpoint_uses_route_telegram_id_for_registration(): void
    {
        $response = $this->postJson('/api/users/111222333/save-id', [
            'telegram' => 'alice',
            'name' => 'Alice',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.telegram', '@alice')
            ->assertJsonPath('user.telegram_id', '111222333')
            ->assertJsonPath('user.name', 'Alice');

        $this->assertDatabaseHas('users', [
            'telegram' => '@alice',
            'telegram_id' => '111222333',
            'name' => 'Alice',
        ]);
    }
}
