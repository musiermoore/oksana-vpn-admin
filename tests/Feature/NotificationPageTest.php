<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Telegram\Bot\Laravel\Facades\Telegram;
use Tests\TestCase;

class NotificationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_page_still_loads_after_welcome_message_form_changes(): void
    {
        $this->actingAs($this->createAdmin())
            ->get('/notifications/create')
            ->assertOk();
    }

    public function test_notification_form_still_sends_message(): void
    {
        $admin = $this->createAdmin();
        $recipient = User::query()->create([
            'name' => 'Client',
            'telegram' => '@client',
            'telegram_id' => '998877',
        ]);

        $telegram = Mockery::mock();
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['chat_id'] === '998877'
                    && $payload['text'] === '<b>Тест</b>'
                    && $payload['parse_mode'] === 'HTML';
            })
            ->andReturnTrue();
        Telegram::swap($telegram);

        $this->actingAs($admin)
            ->post('/notifications', [
                'send_to_all' => false,
                'user_ids' => [$recipient->id],
                'message_html' => '<b>Тест</b>',
            ])
            ->assertRedirect();
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
            'is_admin' => true,
        ]);
    }
}
