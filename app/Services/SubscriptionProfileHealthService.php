<?php

declare(strict_types=1);

namespace App\Services;

final class SubscriptionProfileHealthService
{
    public function __construct(
        private readonly HealthCheckUserService $healthCheckUser,
        private readonly ConnectSubscriptionProfileHealthChecker $connectProfiles,
        private readonly ExternalSubscriptionProfileHealthChecker $externalProfiles,
        private readonly XrayCustomProfileHealthChecker $customProfiles,
    ) {}

    public function check(): void
    {
        $user = $this->healthCheckUser->get();
        $this->connectProfiles->check($user);
        $this->externalProfiles->check($user);
        $this->customProfiles->check($user);
    }
}
