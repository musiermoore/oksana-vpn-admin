<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TelegramAppGiveawayPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_giveaway_page_is_available_and_receives_required_routes(): void
    {
        $this->get('/telegram-app/giveaway')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TelegramApp/Giveaway')
                ->where('routes.giveaway', route('telegram-app.pages.giveaway'))
                ->where('routes.help', route('telegram-app.pages.help'))
                ->where('routes.chats', route('telegram-app.pages.chats'))
            );
    }

    public function test_home_page_receives_giveaway_route_for_navigation(): void
    {
        $this->get('/telegram-app/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TelegramApp/Home')
                ->where('routes.giveaway', route('telegram-app.pages.giveaway'))
            );
    }
}
