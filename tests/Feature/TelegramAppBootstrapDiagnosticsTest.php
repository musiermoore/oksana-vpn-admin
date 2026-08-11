<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramAppBootstrapDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_diagnostic_is_forwarded_to_dev_chat(): void
    {
        Queue::fake();
        config()->set('services.telegram.dev_chat_id', '999999');

        $response = $this
            ->withHeader('User-Agent', 'TelegramTest/1.0')
            ->postJson('/telegram-app/diagnostics/bootstrap', [
                'page' => '/telegram-app/',
                'error_message' => 'Откройте приложение через Telegram.',
                'error_name' => 'Error',
                'attempts' => 3,
                'delay_ms' => 250,
                'href' => 'https://example.com/telegram-app/?tgWebAppStartParam=ticket_1',
                'path' => '/telegram-app/',
                'search' => '?tgWebAppStartParam=ticket_1',
                'referrer' => 'https://t.me/oksanavpn_bot',
                'user_agent' => 'Telegram-WebApp/10.0',
                'timezone' => 'Asia/Omsk',
                'language' => 'ru-RU',
                'telegram_user_id' => '123456',
                'telegram_start_param' => 'ticket_1',
                'telegram_web_app_available' => true,
                'telegram_platform' => 'ios',
                'telegram_version' => '10.0',
                'telegram_color_scheme' => 'light',
                'telegram_init_data_source' => 'query',
                'telegram_init_data_length' => 185,
                'telegram_init_data_keys' => ['auth_date', 'query_id', 'user', 'hash'],
                'telegram_init_data_user_id' => '123456',
                'telegram_init_data_auth_date' => '1723460000',
                'telegram_init_data_query_id_prefix' => 'AAHdF6IQAAAAAN0XohD...',
                'telegram_init_data_hash_prefix' => 'e4d3c2b1a9...',
                'has_stored_token' => false,
                'stored_telegram_user_id' => null,
            ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('message', 'Diagnostic accepted.');

        Queue::assertPushed(SendTelegramMessageJob::class, function (SendTelegramMessageJob $job): bool {
            return $job->payload['chat_id'] === '999999'
                && str_contains($job->payload['text'], 'Mini-app bootstrap failure')
                && str_contains($job->payload['text'], 'Error: Откройте приложение через Telegram.')
                && str_contains($job->payload['text'], 'InitData source: query')
                && str_contains($job->payload['text'], 'Telegram platform: ios');
        });
    }
}
