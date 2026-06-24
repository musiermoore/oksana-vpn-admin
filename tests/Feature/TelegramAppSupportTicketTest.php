<?php

namespace Tests\Feature;

use App\Enums\SupportTicketStatus;
use App\Events\SupportTicketCreated;
use App\Models\SupportTicket;
use App\Models\TelegramAppToken;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramAppSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_support_ticket(): void
    {
        Event::fake([SupportTicketCreated::class]);

        [$user, $token] = $this->createAuthorizedUser();

        $response = $this->withToken($token)->postJson('/telegram-app/support/tickets', [
            'subject' => 'Не проходит оплата',
            'message' => 'Платеж завис на экране подтверждения.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ticket.subject', 'Не проходит оплата')
            ->assertJsonPath('ticket.status', SupportTicketStatus::Open->value)
            ->assertJsonPath('ticket.messages.0.sender_type', 'user');

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'subject' => 'Не проходит оплата',
            'status' => SupportTicketStatus::Open->value,
        ]);

        Event::assertDispatched(SupportTicketCreated::class, function (SupportTicketCreated $event) use ($user) {
            return $event->ticket->user_id === $user->id
                && $event->ticket->subject === 'Не проходит оплата';
        });
    }

    public function test_authenticated_user_can_add_message_to_owned_ticket(): void
    {
        [$user, $token] = $this->createAuthorizedUser();

        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => 'VPN',
            'status' => SupportTicketStatus::Answered,
            'last_message_at' => now(),
        ]);

        $response = $this->withToken($token)->postJson("/telegram-app/support/tickets/{$ticket->id}/messages", [
            'message' => 'Добавляю детали по проблеме.',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('ticket.status', SupportTicketStatus::Open->value)
            ->assertJsonPath('ticket.messages.0.message', 'Добавляю детали по проблеме.');
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function createAuthorizedUser(): array
    {
        $user = User::factory()->create([
            'telegram' => '@alice',
            'telegram_id' => '123456789',
        ]);

        $plainTextToken = str_repeat('b', 80);

        TelegramAppToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
        ]);

        return [$user, $plainTextToken];
    }
}
