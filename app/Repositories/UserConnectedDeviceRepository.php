<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\UserConnectedDevice;

class UserConnectedDeviceRepository
{
    public function findActiveByUserAndAgentHash(int $userId, string $userAgentHash): ?UserConnectedDevice
    {
        return UserConnectedDevice::query()
            ->where('user_id', $userId)
            ->where('user_agent_hash', $userAgentHash)
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
