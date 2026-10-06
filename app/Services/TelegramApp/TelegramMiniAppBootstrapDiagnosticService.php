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
            $this->optionalLine('Error name', $data->errorName, 120),
            'Attempts: '.$data->attempts,
            'Delay ms: '.$data->delayMs,
            'Href: '.$this->limit($data->href, 300),
            'Path: '.$this->limit($data->path, 200),
            $this->optionalLine('Search', $data->search, 400),
            $this->optionalLine('Referrer', $data->referrer, 300),
            'IP: '.$this->limit((string) $request->ip(), 120),
            'Browser UA: '.$this->limit((string) ($data->userAgent ?: $request->userAgent()), 500),
            $this->optionalLine('Timezone', $data->timezone, 120),
            $this->optionalLine('Language', $data->language, 40),
            $this->yesNoLine('Telegram WebApp', $data->telegramWebAppAvailable),
            $this->optionalLine('Telegram platform', $data->telegramPlatform, 120),
            $this->optionalLine('Telegram version', $data->telegramVersion, 120),
            $this->optionalLine('Telegram color scheme', $data->telegramColorScheme, 120),
            $this->optionalLine('Telegram profile user id', $data->telegramUserId, 120),
            $this->optionalLine('Telegram start param', $data->telegramStartParam, 200),
            $this->yesNoLine('Stored token', $data->hasStoredToken),
            $this->optionalLine('Stored telegram user id', $data->storedTelegramUserId, 120),
            'InitData source: '.$this->limit((string) ($data->telegramInitDataSource ?: 'missing'), 120),
            'InitData length: '.$data->telegramInitDataLength,
            $this->initDataKeysLine($data->telegramInitDataKeys),
            $this->optionalLine('InitData user id', $data->telegramInitDataUserId, 120),
            $this->optionalLine('InitData auth_date', $data->telegramInitDataAuthDate, 120),
            $this->optionalLine('InitData query_id prefix', $data->telegramInitDataQueryIdPrefix, 120),
            $this->optionalLine('InitData hash prefix', $data->telegramInitDataHashPrefix, 120),
        ]);

        return Str::limit(implode("\n", $lines), 3900, "\n...");
    }

    private function limit(?string $value, int $limit): string
    {
        return Str::limit(trim((string) $value), $limit, '...');
    }

    private function optionalLine(string $label, ?string $value, int $limit): ?string
    {
        return $value ? $label.': '.$this->limit($value, $limit) : null;
    }

    private function yesNoLine(string $label, bool $value): string
    {
        return $label.': '.($value ? 'yes' : 'no');
    }

    /** @param array<int, string> $keys */
    private function initDataKeysLine(array $keys): ?string
    {
        return $keys !== [] ? 'InitData keys: '.implode(', ', array_slice($keys, 0, 20)) : null;
    }
}
