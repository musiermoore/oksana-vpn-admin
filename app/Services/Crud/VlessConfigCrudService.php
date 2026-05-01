<?php

namespace App\Services\Crud;

use App\DTOs\VlessConfig\VlessConfigStoreData;
use App\DTOs\VlessConfig\VlessConfigUpdateData;
use App\Models\VlessConfig;
use App\Repositories\VlessConfigRepository;
use RuntimeException;

class VlessConfigCrudService
{
    public function __construct(
        private readonly VlessConfigRepository $configs,
    ) {}

    public function assign(VlessConfigStoreData $data): VlessConfig
    {
        $config = $this->configs->findOrFail($data->configId);

        if ($config->user_id) {
            throw new RuntimeException('Конфиг уже привязан к другому человеку');
        }

        return $this->configs->update($config, ['user_id' => $data->userId]);
    }

    public function update(VlessConfig $config, VlessConfigUpdateData $data): VlessConfig
    {
        return $this->configs->update($config, $data->toArray());
    }

    public function unassign(VlessConfig $config): VlessConfig
    {
        return $this->configs->update($config, ['user_id' => null]);
    }
}
