<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiRequestLogPayloadFactory
{
    public function fromRequest(Request $request, ?Response $response): array
    {
        $route = $request->route();

        return [
            'user_id' => $this->resolveUserId($request),
            'action' => $route?->getName() ?? 'api.unknown',
            'method' => $request->method(),
            'endpoint' => $route?->uri() ?? $request->path(),
            'params' => $this->resolveParams($request),
            'request_timezone' => $this->resolveTimezone($request),
            'request_timezone_offset' => $this->resolveTimezoneOffset($request),
            'response_status' => $response?->getStatusCode(),
            'ip_address' => $request->ip(),
            'forwarded_for' => $this->resolveForwardedFor($request),
            'user_agent' => $this->resolveUserAgent($request),
        ];
    }

    private function resolveUserId(Request $request): ?int
    {
        $requestUser = $request->user();

        if ($requestUser instanceof User) {
            return (int) $requestUser->getKey();
        }

        $route = $request->route();
        $boundUser = $route?->parameter('user');

        if ($boundUser instanceof User) {
            return (int) $boundUser->getKey();
        }

        $transaction = $route?->parameter('transaction');

        if ($transaction instanceof Transaction) {
            return (int) $transaction->user_id;
        }

        $userId = $request->integer('user_id');

        if ($userId > 0) {
            return $userId;
        }

        $connectUserId = $this->resolveConnectUserId($request);

        if ($connectUserId !== null) {
            return $connectUserId;
        }

        $telegramId = $route?->parameter('telegramId') ?? $request->input('telegram_id');

        if (is_string($telegramId) && trim($telegramId) !== '') {
            return User::query()
                ->where('telegram_id', trim($telegramId))
                ->value('id');
        }

        $telegram = $route?->parameter('telegram') ?? $request->input('telegram');

        if (is_string($telegram) && $telegram !== '') {
            $normalizedTelegram = str_starts_with($telegram, '@') ? $telegram : '@' . $telegram;

            return User::query()
                ->where('telegram', $normalizedTelegram)
                ->value('id');
        }

        return null;
    }

    private function resolveParams(Request $request): ?array
    {
        $routeParams = collect($request->route()?->parameters() ?? [])
            ->map(fn (mixed $value) => $this->normalizeValue($value))
            ->filter(fn (mixed $value) => $value !== null)
            ->all();

        $body = collect($this->removeSensitiveKeys($request->request->all()))
            ->map(fn (mixed $value) => $this->normalizeValue($value))
            ->filter(fn (mixed $value) => $value !== null)
            ->all();

        $params = array_filter([
            'route' => $routeParams,
            'query' => $this->normalizeValue($request->query()),
            'body' => $body,
        ], fn (mixed $value) => ! empty($value));

        return $params === [] ? null : $params;
    }

    private function removeSensitiveKeys(array $payload): array
    {
        unset(
            $payload['password'],
            $payload['password_confirmation'],
            $payload['current_password'],
        );

        return $payload;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }

    private function resolveTimezone(Request $request): ?string
    {
        $timezone = $this->firstStringValue($request, [
            'timezone',
            'time_zone',
            'tz',
            'X-Timezone',
            'X-Time-Zone',
        ]);

        if ($timezone !== null) {
            return mb_substr(trim($timezone), 0, 100);
        }

        $offset = $this->resolveTimezoneOffset($request);

        return $offset === null ? null : $this->formatUtcOffset($offset);
    }

    private function resolveTimezoneOffset(Request $request): ?int
    {
        $offset = $this->firstIntValue($request, [
            'timezone_offset',
            'tz_offset',
            'utc_offset_minutes',
            'X-Timezone-Offset',
            'X-Time-Zone-Offset',
        ]);

        return $offset === null ? null : max(-840, min(840, $offset));
    }

    private function firstStringValue(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->input($key, $request->header($key));

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function firstIntValue(Request $request, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $request->input($key, $request->header($key));

            if ($value === null || $value === '') {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                return (int) $value;
            }
        }

        return null;
    }

    private function formatUtcOffset(int $minutesFromJavascript): string
    {
        $minutesFromUtc = -1 * $minutesFromJavascript;
        $sign = $minutesFromUtc >= 0 ? '+' : '-';
        $absolute = abs($minutesFromUtc);
        $hours = intdiv($absolute, 60);
        $minutes = $absolute % 60;

        return sprintf('UTC%s%02d:%02d', $sign, $hours, $minutes);
    }

    private function resolveForwardedFor(Request $request): ?string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP', 'X-Forwarded-For', 'Forwarded'] as $header) {
            $value = trim((string) $request->header($header, ''));

            if ($value !== '') {
                return mb_substr($value, 0, 1000);
            }
        }

        return null;
    }

    private function resolveUserAgent(Request $request): ?string
    {
        $userAgent = trim((string) $request->userAgent());

        return $userAgent === '' ? null : mb_substr($userAgent, 0, 1000);
    }

    private function resolveConnectUserId(Request $request): ?int
    {
        $credentials = $this->resolveConnectCredentials($request);

        if ($credentials === null) {
            return null;
        }

        return User::query()
            ->whereKey($credentials['i'])
            ->where('telegram_id', $credentials['tg'])
            ->value('id');
    }

    /**
     * @return array{tg: string, i: int}|null
     */
    private function resolveConnectCredentials(Request $request): ?array
    {
        try {
            if ($request->filled('token')) {
                $payload = Crypt::decrypt($request->string('token')->toString());

                if (! is_array($payload)) {
                    return null;
                }

                $telegramId = (string) ($payload['tg'] ?? '');
                $userId = (int) ($payload['i'] ?? 0);
            } elseif ($request->filled('tg') && $request->filled('i')) {
                $telegramId = (string) Crypt::decrypt($request->string('tg')->toString());
                $userId = (int) Crypt::decrypt($request->string('i')->toString());
            } else {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        if ($telegramId === '' || $userId <= 0) {
            return null;
        }

        return [
            'tg' => $telegramId,
            'i' => $userId,
        ];
    }
}
