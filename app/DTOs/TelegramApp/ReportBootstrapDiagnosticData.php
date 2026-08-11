<?php

declare(strict_types=1);

namespace App\DTOs\TelegramApp;

use App\DTOs\Data;

class ReportBootstrapDiagnosticData extends Data
{
    /**
     * @param  array<int, string>  $telegramInitDataKeys
     */
    public function __construct(
        public string $page,
        public string $errorMessage,
        public ?string $errorName = null,
        public int $attempts = 0,
        public int $delayMs = 0,
        public ?string $href = null,
        public ?string $path = null,
        public ?string $search = null,
        public ?string $referrer = null,
        public ?string $userAgent = null,
        public ?string $timezone = null,
        public ?string $language = null,
        public ?string $telegramUserId = null,
        public ?string $telegramStartParam = null,
        public bool $telegramWebAppAvailable = false,
        public ?string $telegramPlatform = null,
        public ?string $telegramVersion = null,
        public ?string $telegramColorScheme = null,
        public ?string $telegramInitDataSource = null,
        public int $telegramInitDataLength = 0,
        public array $telegramInitDataKeys = [],
        public ?string $telegramInitDataUserId = null,
        public ?string $telegramInitDataAuthDate = null,
        public ?string $telegramInitDataQueryIdPrefix = null,
        public ?string $telegramInitDataHashPrefix = null,
        public bool $hasStoredToken = false,
        public ?string $storedTelegramUserId = null,
    ) {}
}
