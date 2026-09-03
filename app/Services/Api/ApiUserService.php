<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\DTOs\User\ApiUserRegistrationData;
use App\DTOs\User\ApiUserRegistrationResultData;
use App\Models\Config;
use App\Models\PaymentPeriod;
use App\Models\User;
use App\Models\VlessConfig;
use App\Repositories\ConfigRepository;
use App\Repositories\UserRepository;
use App\Repositories\VlessConfigRepository;
use App\Services\ReferralService;
use App\Services\SubscriptionService;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionSyncService;
use App\Services\VlessDeepLinkService;
use App\Support\WireGuardConfigPublicId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ApiUserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ConfigRepository $configs,
        private readonly VlessConfigRepository $vlessConfigs,
        private readonly SubscriptionService $subscriptionService,
        private readonly VlessDeepLinkService $vlessDeepLinks,
        private readonly ReferralService $referrals,
        private readonly VlessExternalSubscriptionSyncService $externalSubscriptions,
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

        [$user, $created] = DB::transaction(function () use ($telegram, $telegramId, $name, $data) {
            $user = $this->users->findByTelegramId($telegramId);

            if ($user) {
                if ($telegram !== '') {
                    $this->users->clearTelegramForOthers($telegram, (int) $user->id);
                }

                $user = $this->users->update($user, [
                    'telegram' => $telegram !== '' ? $telegram : $user->telegram,
                    'name' => $name,
                    'join_at' => $user->join_at ?: now()->toDateString(),
                ]);

                $this->referrals->attachReferral($user, $data->startParam);

                return [
                    $user->fresh(),
                    false,
                ];
            }

            if ($telegram !== '') {
                $user = $this->users->findByTelegram($telegram);

                if ($user) {
                    $this->users->clearTelegramIdForOthers($telegramId, (int) $user->id);

                    $user = $this->users->update($user, [
                        'telegram_id' => $telegramId,
                        'name' => $name,
                        'join_at' => $user->join_at ?: now()->toDateString(),
                    ]);

                    $this->referrals->attachReferral($user, $data->startParam);

                    return [
                        $user->fresh(),
                        false,
                    ];
                }
            }

            $user = $this->users->create([
                'telegram' => $telegram !== '' ? $telegram : null,
                'telegram_id' => $telegramId,
                'name' => $name,
                'join_at' => now()->toDateString(),
            ]);

            $this->referrals->attachReferral($user, $data->startParam);

            return [$user->fresh(), true];
        });

        return new ApiUserRegistrationResultData($user, $created);
    }

    public function getUserConfigs(User $user, string $type): Collection
    {
        if (! $this->isSupportedConfigType($type)) {
            return collect();
        }

        if ($this->isWireGuardType($type)) {
            return $this->getUserWireGuardConfigs($user);
        }

        $configs = $this->isVlessType($type)
            ? $this->vlessConfigs->allForUser($user)
            : $this->configs->allForUser($user);

        $configs = $this->isVlessType($type)
            ? $configs->filter(fn (Model $config) => $this->isVisibleVlessConfig($config))->values()
            : $configs;

        return $user->is_admin
            ? $configs
            : $configs->filter(fn (Model $config) => ! (bool) $config->server?->hide_configs_for_non_admins)->values();
    }

    public function findUserConfig(User $user, string $type, string $configId): ?Model
    {
        if (! $this->isSupportedConfigType($type)) {
            return null;
        }

        if ($this->isWireGuardType($type)) {
            return $this->findUserWireGuardConfig($user, $configId);
        }

        $config = $this->isVlessType($type)
            ? $this->vlessConfigs->findForUser($user, $configId)
            : $this->configs->findForUser($user, $configId);

        if (! $config instanceof Model) {
            return null;
        }

        if ($user->is_admin) {
            return $this->isVisibleVlessConfig($config) ? $config : null;
        }

        if ((bool) $config->server?->hide_configs_for_non_admins) {
            return null;
        }

        return $this->isVisibleVlessConfig($config) ? $config : null;
    }

    private function isVisibleVlessConfig(Model $config): bool
    {
        if (! $config instanceof \App\Models\VlessConfig) {
            return true;
        }

        if (! $config->relationLoaded('xrayInbound')) {
            $config->loadMissing('xrayInbound:id,external_id,is_active');
        }

        return $config->xrayInbound !== null && (bool) $config->xrayInbound->is_active;
    }

    private function isVisibleWireGuardVlessConfig(Model $config): bool
    {
        return $config instanceof VlessConfig
            && $this->isWireGuardFamilyProtocol($config->protocol)
            && $this->isVisibleVlessConfig($config);
    }

    private function isVisibleLegacyWireGuardConfig(Model $config): bool
    {
        if (! $config instanceof Config) {
            return false;
        }

        $server = $config->server;

        if ($server === null || ! $server->isLegacyWireGuardType()) {
            return true;
        }

        return (bool) $server->is_active && (bool) $server->is_ready;
    }

    private function getUserWireGuardConfigs(User $user): Collection
    {
        $configs = $this->configs->allForUser($user)
            ->filter(fn (Model $config) => $this->isVisibleLegacyWireGuardConfig($config))
            ->values()
            ->concat(
                $this->vlessConfigs->allForUser($user)
                    ->filter(fn (Model $config) => $this->isVisibleWireGuardVlessConfig($config))
                    ->values()
            );

        if (! $user->is_admin) {
            $configs = $configs
                ->filter(fn (Model $config) => ! (bool) $config->server?->hide_configs_for_non_admins)
                ->values();
        }

        return $configs
            ->sortBy(fn (Model $config) => sprintf(
                '%010d:%s:%s',
                (int) ($config->server_id ?? 0),
                mb_strtolower((string) ($config->name ?? '')),
                $config instanceof VlessConfig ? '1' : '0',
            ))
            ->values();
    }

    private function findUserWireGuardConfig(User $user, string $configId): ?Model
    {
        $resolved = WireGuardConfigPublicId::decode($configId);

        if (! is_array($resolved)) {
            return null;
        }

        $config = $resolved['source'] === 'xray'
            ? $this->vlessConfigs->findForUser($user, $resolved['id'])
            : $this->configs->findForUser($user, $resolved['id']);

        if (! $config instanceof Model) {
            return null;
        }

        if ($config instanceof Config && ! $this->isVisibleLegacyWireGuardConfig($config)) {
            return null;
        }

        if ($config instanceof VlessConfig && ! $this->isVisibleWireGuardVlessConfig($config)) {
            return null;
        }

        if (! $user->is_admin && (bool) $config->server?->hide_configs_for_non_admins) {
            return null;
        }

        return $config;
    }

    public function getVlessLink(User $user): string
    {
        return $this->vlessDeepLinks->getConnectUrl($user);
    }

    /**
     * @return array<string, string>
     */
    public function getVlessLinks(User $user): array
    {
        return [
            'link' => $this->vlessDeepLinks->getConnectUrl($user),
            'raw_link' => $this->vlessDeepLinks->getConnectUrl($user),
            'show_raw_link' => true,
            ...$this->vlessDeepLinks->getRouteLinks($this->vlessDeepLinks->getDeepLinkRouteParameters($user)),
        ];
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function getVlessWhiteListLinks(User $user): array
    {
        return [
            'link' => $user->is_admin
                ? $this->vlessDeepLinks->getConnectUrlForRoute($user, 'vless.connect-wl')
                : null,
            'raw_link' => $user->is_admin
                ? $this->vlessDeepLinks->getConnectUrlForRoute($user, 'vless.connect-wl')
                : null,
            'show_raw_link' => (bool) $user->is_admin,
            ...$this->vlessDeepLinks->getRouteLinksForRoute(
                'vless.deep-link-wl',
                $this->vlessDeepLinks->getDeepLinkRouteParameters($user)
            ),
        ];
    }

    public function getVlessWhiteListLink(User $user): string
    {
        return $this->vlessDeepLinks->getConnectUrlForRoute($user, 'vless.connect-wl');
    }

    public function hasVisibleVlessWhiteListConfigs(User $user): bool
    {
        return $this->externalSubscriptions->hasVisibleConfigsForUser($user);
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

        return $user->hasActiveSubscription(Carbon::now()->addMonth())
            || $user->getStoredBalanceAmount() >= $requiredAmount;
    }

    public function getSubscriptionPackages(User $user): array
    {
        return $this->subscriptionService->getPackagePricingForUser($user);
    }

    public function isVlessType(string $type): bool
    {
        return trim(mb_strtolower($type)) === 'vless';
    }

    public function isLinkConfigType(string $type): bool
    {
        return $this->isVlessType($type);
    }

    public function isWireGuardType(string $type): bool
    {
        return $this->isWireGuardFamilyProtocol($type);
    }

    public function isSupportedConfigType(string $type): bool
    {
        return $this->isVlessType($type) || $this->isWireGuardType($type);
    }

    private function normalizeTelegram(?string $telegram): string
    {
        $telegram = trim((string) $telegram);

        if ($telegram === '') {
            return '';
        }

        return '@'.ltrim($telegram, '@');
    }

    private function isWireGuardFamilyProtocol(mixed $protocol): bool
    {
        return in_array(trim(mb_strtolower((string) $protocol)), ['wireguard', 'amneziawg'], true);
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
