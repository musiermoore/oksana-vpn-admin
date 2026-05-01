<?php

namespace App\Repositories;

use App\Models\Config;
use App\Models\User;

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
}
