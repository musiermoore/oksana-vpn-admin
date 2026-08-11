<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiveawayAdminTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_giveaway_times_are_saved_in_utc_from_client_timezone(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->post('/giveaways', [
                'title' => 'Timezone test giveaway',
                'description' => 'Testing timezone conversion',
                'starts_at' => '2026-08-11T19:00',
                'ends_at' => '2026-08-11T20:00',
                'client_timezone' => 'Asia/Omsk',
                'auto_repeat_enabled' => false,
                'repeat_delay_minutes' => 60,
                'repeat_limit' => 5,
                'prizes' => [
                    [
                        'duration_months' => 1,
                        'quantity' => 1,
                        'title' => 'Подписка на 1 месяц',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('giveaways', [
            'title' => 'Timezone test giveaway',
            'starts_at' => '2026-08-11 13:00:00',
            'ends_at' => '2026-08-11 14:00:00',
        ]);
    }
}
