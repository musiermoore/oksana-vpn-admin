<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\ConfigGroup;
use App\Models\User;
use App\Models\VlessConfig;
use App\Models\VlessExternalSubscription;
use App\Models\XrayCustomConfig;

final class ConnectV2ConfigGroupPayload
{
    /** @return array<int, array{id:int,name:string,items:array<int,array{model_type:string,model_id:int}>}> */
    public function forUser(User $user): array
    {
        return ConfigGroup::query()->ordered()
            ->with(['vlessConfigs', 'externalSubscriptions.configs', 'xrayCustomConfigs.userAssignments'])
            ->get()
            ->map(function (ConfigGroup $group) use ($user): array {
                $items = [
                    ...$group->vlessConfigs
                        ->filter(fn (VlessConfig $config): bool => (int) $config->user_id === (int) $user->id
                            && $config->is_active && $config->enable)
                        ->map(fn (VlessConfig $config): array => [
                            'model_type' => 'vless_config',
                            'model_id' => (int) $config->id,
                            'name' => (string) $config->name,
                        ])
                        ->all(),
                    ...$group->externalSubscriptions
                        ->filter(fn (VlessExternalSubscription $subscription): bool => $subscription->is_active
                            && $subscription->include_in_connect_v2
                            && ($user->is_admin || $subscription->is_ready))
                        ->map(fn (VlessExternalSubscription $subscription): array => [
                            'model_type' => 'external_subscription',
                            'model_id' => (int) $subscription->id,
                            'name' => (string) $subscription->name,
                            'configs' => $subscription->configs->map(fn ($config): array => [
                                'model_type' => 'external_subscription_config',
                                'model_id' => (int) $config->id,
                                'name' => (string) $config->name,
                            ])->values()->all(),
                        ])
                        ->all(),
                    ...$group->xrayCustomConfigs
                        ->filter(fn (XrayCustomConfig $config): bool => $config->is_active
                            && ($user->is_health_check_user || $config->is_public
                                || $config->userAssignments->contains('user_id', $user->id)))
                        ->map(fn (XrayCustomConfig $config): array => [
                            'model_type' => 'xray_custom_config',
                            'model_id' => (int) $config->id,
                            'name' => (string) $config->name,
                        ])
                        ->all(),
                ];

                return ['id' => (int) $group->id, 'name' => (string) $group->name, 'items' => $items];
            })
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();
    }
}
