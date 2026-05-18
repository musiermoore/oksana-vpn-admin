<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\VlessConfig;
use Illuminate\Database\Eloquent\Collection;

class VlessConfigRepository
{
    public function findOrFail(int $id): VlessConfig
    {
        return VlessConfig::query()->findOrFail($id);
    }

    public function update(VlessConfig $config, array $attributes): VlessConfig
    {
        $config->update($attributes);

        return $config->refresh();
    }

    public function allForUser(User $user): Collection
    {
        return $user->vlessConfigs()
            ->get(['id', 'user_id', 'name']);
    }

    public function findForUser(User $user, int|string $id): ?VlessConfig
    {
        return $user->vlessConfigs()->find($id);
    }
}
