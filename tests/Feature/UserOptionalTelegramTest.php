<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOptionalTelegramTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_without_telegram(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Alice',
                'telegram' => '',
                'join_at' => '2026-06-29',
                'create_configs' => false,
                'max_devices' => 10,
                'traffic_limit_bytes' => 0,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'Alice',
            'telegram' => null,
        ]);
    }

    public function test_user_can_be_updated_without_telegram(): void
    {
        $admin = $this->createAdmin();
        $user = User::query()->create([
            'name' => 'Alice',
            'telegram' => '@alice',
            'join_at' => '2026-06-29',
        ]);

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'Alice Updated',
                'telegram' => '',
                'join_at' => '2026-06-29',
                'is_active' => true,
                'max_devices' => 5,
                'traffic_limit_bytes' => 0,
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Alice Updated',
            'telegram' => null,
        ]);
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'is_admin' => true,
            'join_at' => '2026-06-29',
        ]);
    }
}
