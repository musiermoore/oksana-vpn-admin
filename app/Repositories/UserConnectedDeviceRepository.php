<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserConnectedDevice;

class UserConnectedDeviceRepository
{
    public function findActiveByUserAgentHashAndRoute(
        int $userId,
        string $userAgentHash,
        string $connectionRoute,
    ): ?UserConnectedDevice {
        return UserConnectedDevice::query()
            ->where('user_id', $userId)
            ->where('user_agent_hash', $userAgentHash)
            ->where('connection_route', $connectionRoute)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): UserConnectedDevice
    {
        return UserConnectedDevice::query()->create($attributes);
    }
}
