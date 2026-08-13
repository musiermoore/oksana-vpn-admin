<?php

declare(strict_types=1);

namespace App\Services\ExternalSubscriptions\Incy;

use App\Enums\ExternalSubscriptionSourceFormat;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IncySourceUrlResolver
{
    public function __construct(
        private readonly IncyCryptLinkDecoder $decoder,
    ) {}

    public function resolve(string $sourceUrl, ExternalSubscriptionSourceFormat $sourceFormat): string
    {
        $sourceUrl = trim($sourceUrl);

        return match ($sourceFormat) {
            ExternalSubscriptionSourceFormat::Direct => $sourceUrl,
            ExternalSubscriptionSourceFormat::Incy => $this->resolveIncyUrl($sourceUrl),
            default => throw new RuntimeException('Неизвестный формат источника внешней подписки.'),
        };
    }

    private function resolveIncyUrl(string $sourceUrl): string
    {
        if ($sourceUrl === '') {
            throw new RuntimeException('INCY source URL is empty.');
        }

        if (str_starts_with($sourceUrl, 'incy://')) {
            return $this->decoder->decode($sourceUrl)['url'];
        }

        if (! str_starts_with($sourceUrl, 'https://') && ! str_starts_with($sourceUrl, 'http://')) {
            throw new RuntimeException('Для формата INCY ожидается https:// или incy:// ссылка.');
        }

        return $this->decoder->decode($this->resolveRedirectTarget($sourceUrl))['url'];
    }

    private function resolveRedirectTarget(string $sourceUrl): string
    {
        $response = Http::timeout(max(5, (int) config('incy.redirect.request_timeout_seconds', 20)))
            ->withoutRedirecting()
            ->get($sourceUrl);

        if (! $response->successful() && ! $response->redirect()) {
            throw new RuntimeException('Не удалось загрузить INCY redirect-страницу.');
        }

        $location = trim((string) $response->header('Location', ''));

        if (str_starts_with($location, 'incy://')) {
            return $location;
        }

        $body = (string) $response->body();

        foreach ([
            '/content="[^"]*url=\'?(incy:\/\/[^\'">]+)\'?/i',
            '/href="(incy:\/\/[^"]+)"/i',
            "/href='(incy:\/\/[^']+)'/i",
        ] as $pattern) {
            if (preg_match($pattern, $body, $matches) === 1) {
                return $matches[1];
            }
        }

        throw new RuntimeException('Не удалось найти incy:// redirect в ответе источника.');
    }
}
