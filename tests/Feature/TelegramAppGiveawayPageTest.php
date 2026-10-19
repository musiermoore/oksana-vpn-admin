<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class TelegramAppGiveawayPageTest extends TestCase
{
    public function test_giveaway_page_redirects_to_the_standalone_app(): void
    {
        $this->get('/telegram-app/giveaway')
            ->assertRedirect('https://public.oksana1984.ru/giveaway');
    }

    public function test_home_page_redirects_to_the_standalone_app(): void
    {
        $this->get('/telegram-app/')
            ->assertRedirect('https://public.oksana1984.ru/');
    }

    public function test_old_page_links_preserve_the_path_and_query(): void
    {
        $this->get('/telegram-app/vless-wl?step=links')
            ->assertRedirect('https://public.oksana1984.ru/vless-wl?step=links');

        $this->get('/public/support/42?from=bot')
            ->assertRedirect('https://public.oksana1984.ru/support/42?from=bot');
    }
}
