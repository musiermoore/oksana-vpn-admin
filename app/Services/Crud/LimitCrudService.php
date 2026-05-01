<?php

namespace App\Services\Crud;

use App\DTOs\Limit\LimitData;
use App\Models\Config;
use App\Models\Limit;
use App\Repositories\LimitRepository;
use RuntimeException;

class LimitCrudService
{
    public function __construct(
        private readonly LimitRepository $limits,
    ) {}

    public function create(LimitData $data): Limit
    {
        $config = Config::query()->find($data->configId);

        if (! $config) {
            throw new RuntimeException('Конфиг не найден.');
        }

        if (! $config->setSpeedLimit($data->amount)) {
            throw new RuntimeException('Команда выполнилась с ошибкой.');
        }

        return $this->limits->createForConfig($config, $data->toArray());
    }

    public function delete(Limit $limit): void
    {
        $config = $limit->config;

        if (! $config) {
            throw new RuntimeException('Конфиг не найден.');
        }

        if (! $config->removeSpeedLimit($limit->amount)) {
            throw new RuntimeException('Команда выполнилась с ошибкой.');
        }

        $this->limits->delete($limit);
    }
}
