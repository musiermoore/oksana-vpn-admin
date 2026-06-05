<?php

namespace App\Services\Crud;

use App\DTOs\VlessConfig\VlessConfigStoreData;
use App\DTOs\VlessConfig\VlessConfigUpdateData;
use App\Models\Server;
use App\Models\User;
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
        return $this->assignFromXrayData($data->userId, $data->serverId, $data->inboundId);
    }

    public function assignFromXrayData(int $userId, int $serverId, int $inboundId): VlessConfig
    {
        $user = User::query()->find($userId);
        $server = Server::query()->find($serverId);

        if (! $user) {
            throw new RuntimeException('Пользователь не найден');
        }

        if (! $server || ! $server->is_vless) {
            throw new RuntimeException('Сервер VLESS не найден');
        }

        $xui = new XuiConfigService($server);
        $inbound = collect($xui->getAllVlessInbounds())
            ->first(fn (array $row) => (int) ($row['id'] ?? 0) === $inboundId);

        if (! $inbound) {
            throw new RuntimeException('Выбранный VLESS-вход не найден');
        }

        $existingConfig = $user->vlessConfigs()
            ->where('server_id', $server->id)
            ->where(function ($query) use ($inboundId, $inbound) {
                $query->where('inbound_id', $inboundId)
                    ->orWhere(function ($fallbackQuery) use ($inbound) {
                        $fallbackQuery
                            ->whereNull('inbound_id')
                            ->where('type', $inbound['type'] ?? null);
                    });
            })
            ->first();

        if ($existingConfig) {
            throw new RuntimeException('Для этого входа у пользователя уже есть VLESS-конфиг');
        }

        return $xui->createClientOnAnyInboundId($user, $inboundId);
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
