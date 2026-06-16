<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeMessageAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_messages_page_loads_current_values(): void
    {
        Message::query()->create([
            'name' => 'Welcome Basic',
            'slug' => Message::SLUG_WELCOME_BASIC,
            'text' => 'Привет базовый',
        ]);

        Message::query()->create([
            'name' => 'Welcome Extended',
            'slug' => Message::SLUG_WELCOME_EXTENDED,
            'text' => 'Привет расширенный',
        ]);

        $this->actingAs($this->createAdmin())
            ->get('/messages/welcome')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Messages/EditWelcome')
                ->where('messages.basic_text', 'Привет базовый')
                ->where('messages.extended_text', 'Привет расширенный')
            );
    }

    public function test_welcome_messages_can_be_saved(): void
    {
        $this->actingAs($this->createAdmin())
            ->put('/messages/welcome', [
                'basic_text' => '<b>Базовый</b>',
                'extended_text' => '<i>Расширенный</i>',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'slug' => Message::SLUG_WELCOME_BASIC,
            'text' => '<b>Базовый</b>',
        ]);

        $this->assertDatabaseHas('messages', [
            'slug' => Message::SLUG_WELCOME_EXTENDED,
            'text' => '<i>Расширенный</i>',
        ]);
    }

    public function test_welcome_messages_can_be_saved_as_empty_strings(): void
    {
        Message::query()->create([
            'name' => 'Welcome Basic',
            'slug' => Message::SLUG_WELCOME_BASIC,
            'text' => 'Old basic',
        ]);

        Message::query()->create([
            'name' => 'Welcome Extended',
            'slug' => Message::SLUG_WELCOME_EXTENDED,
            'text' => 'Old extended',
        ]);

        $this->actingAs($this->createAdmin())
            ->put('/messages/welcome', [
                'basic_text' => '',
                'extended_text' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'slug' => Message::SLUG_WELCOME_BASIC,
            'text' => '',
        ]);

        $this->assertDatabaseHas('messages', [
            'slug' => Message::SLUG_WELCOME_EXTENDED,
            'text' => '',
        ]);
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
