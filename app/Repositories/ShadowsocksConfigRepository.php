<?php

namespace App\Repositories;

use App\Models\ShadowsocksConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ShadowsocksConfigRepository
{
    public function findOrFail(int $id): ShadowsocksConfig
    {
        return ShadowsocksConfig::query()->findOrFail($id);
    }

    public function update(ShadowsocksConfig $config, array $attributes): ShadowsocksConfig
    {
        $config->update($attributes);

        return $config->refresh();
    }

    public function allForUser(User $user): Collection
    {
        return $user->shadowsocksConfigs()
            ->get(['id', 'user_id', 'name']);
    }

    public function findForUser(User $user, int|string $id): ?ShadowsocksConfig
    {
        return $user->shadowsocksConfigs()->find($id);
    }
}
