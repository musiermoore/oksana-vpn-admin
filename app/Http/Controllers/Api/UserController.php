<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentPeriod;
use App\Models\User;
use App\Support\BotApiMessages;
use App\Services\UserApiService;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class UserController extends Controller
{
    public function registrationStatus(string $telegramId)
    {
        try {
            $user = User::query()
                ->with([
                    'activeSubscription' => function ($query) {
                        $query->select([
                            'user_subscriptions.id',
                            'user_subscriptions.user_id',
                            'user_subscriptions.end_date',
                        ]);
                    },
                ])
                ->select([
                    'users.id',
                    'users.telegram_id',
                    'users.balance',
                ])
                ->where('telegram_id', trim($telegramId))
                ->where('users.is_active', true)
                ->whereNull('users.deleted_at')
                ->first();

            if (! $user) {
                return response()->json([
                    'registered' => false,
                    'active_subscription_end_date' => null,
                    'has_money_for_next_subscription_month' => false,
                ]);
            }

            return response()->json([
                'registered' => true,
                'active_subscription_end_date' => $user->activeSubscription?->end_date,
                'has_money_for_next_subscription_month' => $this->hasMoneyForNextSubscriptionMonth($user),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function balance()
    {
        $user = request()->attributes->get('apiUser');

        return response()->json([
            'balance' => max(0, $user->balance),
            'debt' => max(0, -$user->balance)
        ]);
    }

    public function getUserConfigs(string $type)
    {
        $user = request()->attributes->get('apiUser');

        $configs = $type === 'vless'
            ? $user->vlessConfigs
            : $user->configs;

        return response()->json([
            'configs' => $configs,
        ]);
    }

    public function downloadConfig(string $type, string $configId)
    {
        $user = request()->attributes->get('apiUser');

        $query = $type === 'vless'
            ? $user->vlessConfigs()
            : $user->configs();

        $config = $query->find($configId);

        if (empty($config)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            return $type === 'vless'
                ? response($config->getLink())
                : response()->download($config->path, $config->name . '.conf');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function downloadQrCode(string $type, string $configId)
    {
        $user = request()->attributes->get('apiUser');

        $query = $type === 'vless'
            ? $user->vlessConfigs()
            : $user->configs();

        $config = $query->find($configId);

        if (empty($config)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            $configBody = $config->getQrCodeContent();

            $png = QrCode::format('png')->margin(5)->size(512)->generate($configBody);

            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="qrcode.png"');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function register(Request $request)
    {
        $payload = $request->validate([
            'telegram' => ['nullable', 'string', 'max:255'],
            'telegram_id' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $telegram = $this->normalizeTelegram((string) ($payload['telegram'] ?? ''));
        $telegramId = trim($payload['telegram_id']);
        $name = trim((string) ($payload['name'] ?? '')) ?: ($telegram !== '' ? ltrim($telegram, '@') : $telegramId);

        [$user, $created] = DB::transaction(function () use ($telegram, $telegramId, $name) {
            $user = User::query()->where('telegram_id', $telegramId)->first();

            if ($user) {
                if ($telegram !== '') {
                    User::query()
                        ->where('telegram', $telegram)
                        ->whereKeyNot($user->id)
                        ->update(['telegram' => null]);
                }

                $user->update([
                    'telegram' => $telegram !== '' ? $telegram : $user->telegram,
                    'name' => $name,
                    'join_at' => $user->join_at ?: now()->toDateString(),
                ]);

                return [$user->refresh(), false];
            }

            if ($telegram !== '') {
                $user = User::query()->where('telegram', $telegram)->first();

                if ($user) {
                    User::query()
                        ->where('telegram_id', $telegramId)
                        ->whereKeyNot($user->id)
                        ->update(['telegram_id' => null]);

                    $user->update([
                        'telegram_id' => $telegramId,
                        'name' => $name,
                        'join_at' => $user->join_at ?: now()->toDateString(),
                    ]);

                    return [$user->refresh(), false];
                }
            }

            $user = User::query()->create([
                'telegram' => $telegram !== '' ? $telegram : null,
                'telegram_id' => $telegramId,
                'name' => $name,
                'join_at' => now()->toDateString(),
            ]);

            return [$user, true];
        });

        return response()->json([
            'message' => $created
                ? 'Регистрация выполнена. Теперь можно пользоваться ботом.'
                : 'Telegram успешно привязан.',
            'user' => [
                'id' => $user->id,
                'telegram' => $user->telegram,
                'telegram_id' => $user->telegram_id,
                'name' => $user->name,
            ],
        ], $created ? 201 : 200);
    }

    public function saveTelegramId(Request $request, string $telegramId)
    {
        $request->merge(['telegram_id' => $telegramId]);

        return $this->register($request);
    }

    public function getVlessLink()
    {
        $result = $this->resolveVlessLink();

        if ($result instanceof Response) {
            return $result;
        }

        return response($result);
    }

    public function getVlessQrCode()
    {
        $result = $this->resolveVlessLink();

        if ($result instanceof Response) {
            return $result;
        }

        try {
            $png = QrCode::format('png')->margin(5)->size(512)->generate($result);

            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="vless-qrcode.png"');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    private function resolveVlessLink(): Response|string
    {
        $user = request()->attributes->get('apiUser');

        $link = route('vless.connect', [
            'tg' => Crypt::encrypt($user->telegram_id),
            'i' => Crypt::encrypt($user->id),
        ], absolute: false);

        return config('vless.domain') . $link;
    }

    private function normalizeTelegram(string $telegram): string
    {
        $telegram = trim($telegram);

        if ($telegram === '') {
            return '';
        }

        return '@' . ltrim($telegram, '@');
    }

    private function hasMoneyForNextSubscriptionMonth(User $user): bool
    {
        $activePaymentPeriod = PaymentPeriod::getActive();

        if (! $activePaymentPeriod) {
            return false;
        }

        $extraAmount = (float) $user->extraPayments()
            ->where('current_payment_id', $activePaymentPeriod->id)
            ->sum('amount');

        $requiredAmount = (float) $activePaymentPeriod->amount + $extraAmount;

        return $user->getStoredBalanceAmount() >= $requiredAmount;
    }
}
