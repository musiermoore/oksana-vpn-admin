<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class TelegramAppCorsTest extends TestCase
{
    public function test_telegram_app_api_preflight_accepts_the_new_ui_origin(): void
    {
        $this->withHeaders([
            'Origin' => 'https://public.oksana1984.ru',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type,x-client-timezone',
        ])->options('/telegram-app/auth/telegram')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods');
    }

    public function test_telegram_app_response_exposes_download_filename_header(): void
    {
        $this->withHeader('Origin', 'https://public.oksana1984.ru')
            ->getJson('/telegram-app/me')
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Expose-Headers', 'Content-Disposition');
    }
}
