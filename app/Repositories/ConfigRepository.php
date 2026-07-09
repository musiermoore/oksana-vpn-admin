<?php

namespace App\Repositories;

use App\Models\Config;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ConfigRepository
{
    public function update(Config $config, array $attributes): Config
    {
        $config->update($attributes);

        return $config->refresh();
    }

    public function delete(Config $config): void
    {
        $config->delete();
    }

    public function createForUser(User $user, array $attributes): Config
    {
        return $user->configs()->create($attributes);
    }

    public function allForUser(User $user): Collection
    {
        return $user->configs()
            ->with('server:id,hide_configs_for_non_admins')
            ->get(['id', 'user_id', 'server_id', 'name']);
    }

    public function findForUser(User $user, int|string $id): ?Config
    {
        return $user->configs()
            ->with('server')
            ->find($id);
    }
}
