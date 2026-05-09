<?php

namespace App\Services\Crud;

use App\DTOs\VlessConfig\VlessConfigStoreData;
use App\DTOs\VlessConfig\VlessConfigUpdateData;
use App\Models\Server;
use App\Models\VlessConfig;
use App\Repositories\VlessConfigRepository;
use App\Services\XuiConfigService;
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

    public function enable(VlessConfig $config): VlessConfig
    {
        $this->getXuiConfigService($config)->enableClient($config->uuid);

        return $this->configs->update($config, ['enable' => true]);
    }

    public function disable(VlessConfig $config): VlessConfig
    {
        $this->getXuiConfigService($config)->disableClient($config->uuid);

        return $this->configs->update($config, ['enable' => false]);
    }

    private function getXuiConfigService(VlessConfig $config): XuiConfigService
    {
        $server = Server::query()->find($config->server_id);

        if (! $server) {
            throw new RuntimeException('Сервер для VLESS-конфига не найден');
        }

        return new XuiConfigService($server);
    }
}
