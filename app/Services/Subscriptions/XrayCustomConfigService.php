<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTOs\Subscription\SubscriptionBuildResult;
use App\Models\User;
use App\Models\XrayCustomConfig;
use App\Services\Subscriptions\Builders\ConnectJsonBuilder;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionSyncService;

class XrayCustomConfigService
{
    public function __construct(
        private readonly UserSubscriptionService $subscriptions,
        private readonly ConnectJsonBuilder $builder,
    ) {}

    public function build(User $user, XrayCustomConfig $config, ?string $client = null): SubscriptionBuildResult
    {
        $config->loadMissing(['dnsSettings', 'geodata', 'clientGeodata', 'outboundGroups.fallbackGroup', 'routes']);

        return $this->builder->buildForCustomConfig(
            $this->subscriptions->buildNamedNodes($user, VlessExternalSubscriptionSyncService::PURPOSE_CUSTOM),
            $config,
            $client,
        );
    }
}
