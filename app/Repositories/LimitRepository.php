<?php

namespace App\Repositories;

use App\Models\Config;
use App\Models\Limit;

class LimitRepository
{
    public function createForConfig(Config $config, array $attributes): Limit
    {
        return $config->limits()->create($attributes);
    }

    public function delete(Limit $limit): void
    {
        $limit->delete();
    }
}
