<?php

namespace App\Repositories;

use App\Models\VlessConfig;

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
}
