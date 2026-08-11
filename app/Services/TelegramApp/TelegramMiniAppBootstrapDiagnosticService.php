<?php

declare(strict_types=1);

namespace App\Services\TelegramApp;

use App\DTOs\TelegramApp\ReportBootstrapDiagnosticData;
use App\Services\TelegramDevChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramMiniAppBootstrapDiagnosticService
{
    public function __construct(
        private readonly TelegramDevChatService $devChat,
    ) {}

    public function report(ReportBootstrapDiagnosticData $data, Request $request): void
    {
        $context = [
            'page' => $data->page,
            'error_message' => $data->errorMessage,
            'error_name' => $data->errorName,
            'attempts' => $data->attempts,
            'delay_ms' => $data->delayMs,
            'href' => $data->href,
            'path' => $data->path,
            'search' => $data->search,
            'referrer' => $data->referrer,
            'user_agent' => $data->userAgent,
            'timezone' => $data->timezone,
            'language' => $data->language,
            'telegram_user_id' => $data->telegramUserId,
            'telegram_start_param' => $data->telegramStartParam,
            'telegram_web_app_available' => $data->telegramWebAppAvailable,
            'telegram_platform' => $data->telegramPlatform,
            'telegram_version' => $data->telegramVersion,
            'telegram_color_scheme' => $data->telegramColorScheme,
            'telegram_init_data_source' => $data->telegramInitDataSource,
            'telegram_init_data_length' => $data->telegramInitDataLength,
            'telegram_init_data_keys' => $data->telegramInitDataKeys,
            'telegram_init_data_user_id' => $data->telegramInitDataUserId,
            'telegram_init_data_auth_date' => $data->telegramInitDataAuthDate,
            'telegram_init_data_query_id_prefix' => $data->telegramInitDataQueryIdPrefix,
            'telegram_init_data_hash_prefix' => $data->telegramInitDataHashPrefix,
            'has_stored_token' => $data->hasStoredToken,
            'stored_telegram_user_id' => $data->storedTelegramUserId,
            'request_ip' => $request->ip(),
            'request_user_agent' => $request->userAgent(),
        ];

        Log::warning('telegram-mini-app.bootstrap-diagnostic', $context);

        $this->devChat->send($this->buildMessage($data, $request));
    }

    private function buildMessage(ReportBootstrapDiagnosticData $data, Request $request): string
    {
        $lines = array_filter([
            'Mini-app bootstrap failure',
            'Env: '.config('app.env'),
            'Page: '.$this->limit($data->page, 120),
            'Error: '.$this->limit($data->errorMessage, 500),
            $data->errorName ? 'Error name: '.$this->limit($data->errorName, 120) : null,
            'Attempts: '.$data->attempts,
            'Delay ms: '.$data->delayMs,
            'Href: '.$this->limit($data->href, 300),
            'Path: '.$this->limit($data->path, 200),
            $data->search ? 'Search: '.$this->limit($data->search, 400) : null,
            $data->referrer ? 'Referrer: '.$this->limit($data->referrer, 300) : null,
            'IP: '.$this->limit((string) $request->ip(), 120),
            'Browser UA: '.$this->limit((string) ($data->userAgent ?: $request->userAgent()), 500),
            $data->timezone ? 'Timezone: '.$this->limit($data->timezone, 120) : null,
            $data->language ? 'Language: '.$this->limit($data->language, 40) : null,
            'Telegram WebApp: '.($data->telegramWebAppAvailable ? 'yes' : 'no'),
            $data->telegramPlatform ? 'Telegram platform: '.$this->limit($data->telegramPlatform, 120) : null,
            $data->telegramVersion ? 'Telegram version: '.$this->limit($data->telegramVersion, 120) : null,
            $data->telegramColorScheme ? 'Telegram color scheme: '.$this->limit($data->telegramColorScheme, 120) : null,
            $data->telegramUserId ? 'Telegram profile user id: '.$this->limit($data->telegramUserId, 120) : null,
            $data->telegramStartParam ? 'Telegram start param: '.$this->limit($data->telegramStartParam, 200) : null,
            'Stored token: '.($data->hasStoredToken ? 'yes' : 'no'),
            $data->storedTelegramUserId ? 'Stored telegram user id: '.$this->limit($data->storedTelegramUserId, 120) : null,
            'InitData source: '.$this->limit((string) ($data->telegramInitDataSource ?: 'missing'), 120),
            'InitData length: '.$data->telegramInitDataLength,
            ! empty($data->telegramInitDataKeys) ? 'InitData keys: '.implode(', ', array_slice($data->telegramInitDataKeys, 0, 20)) : null,
            $data->telegramInitDataUserId ? 'InitData user id: '.$this->limit($data->telegramInitDataUserId, 120) : null,
            $data->telegramInitDataAuthDate ? 'InitData auth_date: '.$this->limit($data->telegramInitDataAuthDate, 120) : null,
            $data->telegramInitDataQueryIdPrefix ? 'InitData query_id prefix: '.$this->limit($data->telegramInitDataQueryIdPrefix, 120) : null,
            $data->telegramInitDataHashPrefix ? 'InitData hash prefix: '.$this->limit($data->telegramInitDataHashPrefix, 120) : null,
        ]);

        return Str::limit(implode("\n", $lines), 3900, "\n...");
    }

    private function limit(?string $value, int $limit): string
    {
        return Str::limit(trim((string) $value), $limit, '...');
    }
}
