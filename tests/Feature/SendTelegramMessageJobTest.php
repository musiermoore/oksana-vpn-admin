<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendTelegramMessageJob;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\TelegramRequest;
use Telegram\Bot\TelegramResponse;
use Tests\TestCase;

class SendTelegramMessageJobTest extends TestCase
{
    public function test_job_skips_blocked_user_without_reporting_exception(): void
    {
        Exceptions::fake();

        $telegram = Mockery::mock();
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->andThrow($this->telegramException(403, 'Forbidden: bot was blocked by the user'));

        Telegram::swap($telegram);

        (new SendTelegramMessageJob([
            'chat_id' => '123456789',
            'text' => 'Hello',
        ]))->handle();

        Exceptions::assertNothingReported();
    }

    public function test_job_skips_missing_chat_without_reporting_exception(): void
    {
        Exceptions::fake();

        $telegram = Mockery::mock();
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->andThrow($this->telegramException(400, 'Bad Request: chat not found'));

        Telegram::swap($telegram);

        (new SendTelegramMessageJob([
            'chat_id' => '123456789',
            'text' => 'Hello',
        ]))->handle();

        Exceptions::assertNothingReported();
    }

    public function test_job_reports_unexpected_exception(): void
    {
        Exceptions::fake();

        $exception = new RuntimeException('Telegram is unavailable.');

        $telegram = Mockery::mock();
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->andThrow($exception);

        Telegram::swap($telegram);

        (new SendTelegramMessageJob([
            'chat_id' => '123456789',
            'text' => 'Hello',
        ]))->handle();

        Exceptions::assertReported(fn (RuntimeException $reported): bool => $reported === $exception);
    }

    private function telegramException(int $statusCode, string $description): TelegramResponseException
    {
        return TelegramResponseException::create(new TelegramResponse(
            new TelegramRequest('token', 'POST', 'sendMessage'),
            new Response($statusCode, [], json_encode([
                'ok' => false,
                'error_code' => $statusCode,
                'description' => $description,
            ], JSON_THROW_ON_ERROR))
        ));
    }
}
