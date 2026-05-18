<?php

namespace App\Services\Api;

use App\DTOs\User\ApiUserRegistrationData;
use App\DTOs\User\ApiUserRegistrationResultData;
use App\Models\PaymentPeriod;
use App\Models\User;
use App\Repositories\ConfigRepository;
use App\Repositories\UserRepository;
use App\Repositories\VlessConfigRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ApiUserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ConfigRepository $configs,
        private readonly VlessConfigRepository $vlessConfigs,
    ) {}

    public function findUserByTelegramId(string $telegramId): ?User
    {
        return $this->users->findApiUserByTelegramId($telegramId);
    }

    public function findActiveUserByTelegramId(string $telegramId): ?User
    {
        return $this->users->findActiveApiUserByTelegramId($telegramId);
    }

    public function register(ApiUserRegistrationData $data): ApiUserRegistrationResultData
    {
        $telegram = $this->normalizeTelegram($data->telegram);
        $telegramId = trim($data->telegramId);
        $name = $this->resolveName($telegram, $telegramId, $data->name);

        [$user, $created] = DB::transaction(function () use ($telegram, $telegramId, $name) {
            $user = $this->users->findByTelegramId($telegramId);

            if ($user) {
                if ($telegram !== '') {
                    $this->users->clearTelegramForOthers($telegram, (int) $user->id);
                }

                return [
                    $this->users->update($user, [
                        'telegram' => $telegram !== '' ? $telegram : $user->telegram,
                        'name' => $name,
                        'join_at' => $user->join_at ?: now()->toDateString(),
                    ]),
                    false,
                ];
            }

            if ($telegram !== '') {
                $user = $this->users->findByTelegram($telegram);

                if ($user) {
                    $this->users->clearTelegramIdForOthers($telegramId, (int) $user->id);

                    return [
                        $this->users->update($user, [
                            'telegram_id' => $telegramId,
                            'name' => $name,
                            'join_at' => $user->join_at ?: now()->toDateString(),
                        ]),
                        false,
                    ];
                }
            }

            return [
                $this->users->create([
                    'telegram' => $telegram !== '' ? $telegram : null,
                    'telegram_id' => $telegramId,
                    'name' => $name,
                    'join_at' => now()->toDateString(),
                ]),
                true,
            ];
        });

        return new ApiUserRegistrationResultData($user, $created);
    }

    public function getUserConfigs(User $user, string $type): Collection
    {
        return $this->isVlessType($type)
            ? $this->vlessConfigs->allForUser($user)
            : $this->configs->allForUser($user);
    }

    public function findUserConfig(User $user, string $type, string $configId): ?Model
    {
        return $this->isVlessType($type)
            ? $this->vlessConfigs->findForUser($user, $configId)
            : $this->configs->findForUser($user, $configId);
    }

    public function getVlessLink(User $user): string
    {
        $link = route('vless.connect', [
            'tg' => Crypt::encrypt($user->telegram_id),
            'i' => Crypt::encrypt($user->id),
        ], absolute: false);

        return config('vless.domain') . $link;
    }

    public function hasMoneyForNextSubscriptionMonth(User $user): bool
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

    public function isVlessType(string $type): bool
    {
        return trim(mb_strtolower($type)) === 'vless';
    }

    private function normalizeTelegram(string $telegram): string
    {
        $telegram = trim($telegram);

        if ($telegram === '') {
            return '';
        }

        return '@' . ltrim($telegram, '@');
    }

    private function resolveName(string $telegram, string $telegramId, ?string $name): string
    {
        $name = trim((string) $name);

        if ($name !== '') {
            return $name;
        }

        return $telegram !== '' ? ltrim($telegram, '@') : $telegramId;
    }
}
