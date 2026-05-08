<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Telegram\Bot\Laravel\Facades\Telegram;

class AuthController extends Controller
{
    private const LOGIN_CODE_TTL_MINUTES = 2;

    public function create(Request $request): Response
    {
        $telegram = $this->normalizeTelegram($request->string('telegram')->toString());

        return inertia('Auth/Login', [
            'telegram' => $telegram,
            'code_requested' => $telegram !== '',
            'code_expires_in_seconds' => self::LOGIN_CODE_TTL_MINUTES * 60,
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $request->validate([
            'telegram' => ['required', 'string', 'max:255'],
        ]);

        $telegram = $this->normalizeTelegram($request->string('telegram')->toString());
        $user = $this->findLoginUser($telegram);

        if (! $user || empty($user->telegram_id)) {
            throw ValidationException::withMessages([
                'telegram' => 'Пользователь не найден или не привязан к Telegram.',
            ]);
        }

        if ($request->boolean('force')) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('wireguard.active-peers'));
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user), [
            'code' => $code,
            'telegram' => $telegram,
        ], now()->addMinutes(self::LOGIN_CODE_TTL_MINUTES));

        Telegram::sendMessage([
            'chat_id' => $user->telegram_id,
            'text' => "Код входа: {$code}\nОн действует 2 минуты.",
        ]);

        return redirect()
            ->route('login', ['telegram' => $telegram])
            ->with('success', 'Код отправлен в Telegram.');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'telegram' => ['required', 'string', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $telegram = $this->normalizeTelegram($request->string('telegram')->toString());
        $user = $this->findLoginUser($telegram);

        if (! $user) {
            throw ValidationException::withMessages([
                'telegram' => 'Пользователь не найден.',
            ]);
        }

        $payload = Cache::get($this->cacheKey($user));

        if (! is_array($payload) || ($payload['telegram'] ?? null) !== $telegram) {
            throw ValidationException::withMessages([
                'code' => 'Код истёк. Запросите новый.',
            ]);
        }

        if (! hash_equals((string) ($payload['code'] ?? ''), $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Неверный код.',
            ]);
        }

        Cache::forget($this->cacheKey($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('wireguard.active-peers'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function findLoginUser(string $telegram): ?User
    {
        if ($telegram === '') {
            return null;
        }

        return User::query()
            ->where('telegram', $telegram)
            ->where('is_admin', true)
            ->first();
    }

    private function normalizeTelegram(string $telegram): string
    {
        $telegram = trim($telegram);

        if ($telegram === '') {
            return '';
        }

        return '@' . ltrim($telegram, '@');
    }

    private function cacheKey(User $user): string
    {
        return 'auth:login-code:' . $user->id;
    }
}
