<?php

declare(strict_types=1);

namespace App\Services\TelegramApp;

use App\DTOs\TelegramApp\TelegramAppAuthData;
use App\DTOs\TelegramApp\TelegramAppPasswordAuthData;
use App\DTOs\TelegramApp\TelegramAppPasswordRegistrationData;
use App\DTOs\User\ApiUserRegistrationData;
use App\Models\TelegramAppToken;
use App\Models\User;
use App\Repositories\TelegramAppTokenRepository;
use App\Services\Api\ApiUserService;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TelegramMiniAppAuthService
{
    public function __construct(
        private readonly ApiUserService $apiUserService,
        private readonly TelegramAppTokenRepository $tokens,
    ) {}

    /**
     * @return array{user: User, token: string, expires_at: string|null}
     */
    public function authenticate(TelegramAppAuthData $data): array
    {
        $payload = $this->validateInitData($data->initData);
        $userData = Arr::get($payload, 'user');

        if (! is_array($userData)) {
            throw new DomainException('Telegram не передал данные пользователя.');
        }

        $telegramId = trim((string) ($userData['id'] ?? ''));

        if ($telegramId === '') {
            throw new DomainException('Не удалось определить Telegram ID пользователя.');
        }

        $username = trim((string) ($userData['username'] ?? ''));
        $firstName = trim((string) ($userData['first_name'] ?? ''));
        $lastName = trim((string) ($userData['last_name'] ?? ''));
        $displayName = trim(trim($firstName.' '.$lastName));
        $startParam = trim((string) ($payload['start_param'] ?? $data->startParam ?? ''));

        $result = $this->apiUserService->register(new ApiUserRegistrationData(
            telegramId: $telegramId,
            telegram: $username,
            name: $displayName !== '' ? $displayName : ($username !== '' ? $username : $telegramId),
            startParam: $startParam !== '' ? $startParam : null,
        ));

        return $this->issueToken($result->user);
    }

    /**
     * @return array{user: User, token: string, expires_at: string|null}
     */
    public function authenticateWithPassword(TelegramAppPasswordAuthData $data): array
    {
        $login = trim($data->login);
        $password = $data->password;

        $user = User::query()
            ->where('login', $login)
            ->first();

        if (! $user || empty($user->password) || ! Hash::check($password, $user->password)) {
            throw new DomainException('Неверный логин или пароль.');
        }

        return $this->issueToken($user);
    }

    /**
     * @return array{user: User, token: string, expires_at: string|null}
     */
    public function registerWithPassword(TelegramAppPasswordRegistrationData $data): array
    {
        $name = trim($data->name);
        $login = trim($data->login);

        if ($name === '' || $login === '') {
            throw new DomainException('Заполните имя, логин и пароль.');
        }

        $existingUser = User::query()
            ->where('login', $login)
            ->first();

        if ($existingUser) {
            throw new DomainException('Этот логин уже занят.');
        }

        $user = User::query()->create([
            'name' => $name,
            'login' => $login,
            'password' => $data->password,
            'join_at' => now()->toDateString(),
            'is_admin' => false,
        ]);

        return $this->issueToken($user);
    }

    public function resolveUserByToken(?string $plainTextToken): ?User
    {
        $plainTextToken = trim((string) $plainTextToken);

        if ($plainTextToken === '') {
            return null;
        }

        $token = $this->tokens->findByTokenHash(hash('sha256', $plainTextToken));

        if (! $token instanceof TelegramAppToken || $token->isExpired()) {
            return null;
        }

        $this->tokens->touchLastUsed($token);

        return $token->user;
    }

    public function logout(?string $plainTextToken): void
    {
        $plainTextToken = trim((string) $plainTextToken);

        if ($plainTextToken === '') {
            return;
        }

        $token = $this->tokens->findByTokenHash(hash('sha256', $plainTextToken));

        if ($token instanceof TelegramAppToken) {
            $this->tokens->delete($token);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateInitData(string $initData): array
    {
        $botToken = (string) config('services.telegram.bot_token', '');

        if ($botToken === '') {
            throw new DomainException('TELEGRAM_BOT_TOKEN не настроен.');
        }

        parse_str($initData, $parsed);

        $hash = trim((string) ($parsed['hash'] ?? ''));

        if ($hash === '') {
            throw new DomainException('Telegram init data не содержит hash.');
        }

        unset($parsed['hash']);

        ksort($parsed);

        $dataCheckString = collect($parsed)
            ->map(fn ($value, $key) => $key.'='.$this->normalizeInitValue($value))
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculatedHash, $hash)) {
            throw new DomainException('Не удалось подтвердить Telegram WebApp сессию.');
        }

        $authDate = (int) ($parsed['auth_date'] ?? 0);
        $maxAge = max(60, (int) config('services.telegram.mini_app_init_data_ttl_seconds', 3600));

        if ($authDate <= 0 || Carbon::createFromTimestamp($authDate)->addSeconds($maxAge)->isPast()) {
            throw new DomainException('Telegram WebApp сессия истекла. Откройте mini-app заново.');
        }

        if (isset($parsed['user']) && is_string($parsed['user'])) {
            $parsed['user'] = json_decode($parsed['user'], true);
        }

        return $parsed;
    }

    private function normalizeInitValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return trim((string) $value);
    }

    private function resolveExpiration(): ?Carbon
    {
        $ttlMinutes = (int) config('services.telegram.mini_app_token_ttl_minutes', 43200);

        return $ttlMinutes > 0 ? now()->addMinutes($ttlMinutes) : null;
    }

    /**
     * @return array{user: User, token: string, expires_at: string|null}
     */
    private function issueToken(User $user): array
    {
        $plainTextToken = Str::random(80);
        $expiresAt = $this->resolveExpiration();

        $this->tokens->createForUser($user, [
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => $expiresAt,
            'last_used_at' => now(),
        ]);

        return [
            'user' => $user->fresh(),
            'token' => $plainTextToken,
            'expires_at' => $expiresAt?->toAtomString(),
        ];
    }
}
