<?php

declare(strict_types=1);

namespace App\Services\Crud;

use App\DTOs\VlessConfig\VlessConfigStoreData;
use App\DTOs\VlessConfig\VlessConfigUpdateData;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use App\Repositories\VlessConfigRepository;
use App\Services\ConfigGroupAssignmentService;
use App\Services\XuiConfigService;
use App\Services\XuiConfigServiceFactory;
use RuntimeException;

class VlessConfigCrudService
{
    public function __construct(
        private readonly VlessConfigRepository $configs,
        private readonly ConfigGroupAssignmentService $configGroups,
    ) {}

    public function assign(VlessConfigStoreData $data): VlessConfig
    {
        $config = $this->assignFromXrayData($data->userId, $data->serverId, $data->inboundId);

        return $this->update($config, new VlessConfigUpdateData($data->userId, $data->groupId, $data->subtitle));
    }

    public function assignFromXrayData(int $userId, int $serverId, int $inboundId): VlessConfig
    {
        $user = User::query()->find($userId);
        $server = Server::query()->find($serverId);

        if (! $user) {
            throw new RuntimeException('Пользователь не найден');
        }

        if (! $server || ! $server->isVlessType() || ! $server->is_active) {
            throw new RuntimeException('Сервер VLESS не найден');
        }

        if (! in_array($inboundId, $server->getAvailableInboundIdsForUser($user), true)) {
            throw new RuntimeException('Выбранный VLESS-вход недоступен для пользователя');
        }

        $xui = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
        $inbound = collect($xui->getAllVlessInbounds())
            ->first(fn (array $row) => (int) ($row['id'] ?? 0) === $inboundId);

        if (! $inbound) {
            throw new RuntimeException('Выбранный VLESS-вход не найден');
        }

        $existingConfig = $user->vlessConfigs()
            ->where('server_id', $server->id)
            ->where(function ($query) use ($inboundId, $inbound) {
                $query->whereHas('xrayInbound', fn ($xrayInboundQuery) => $xrayInboundQuery->where('external_id', $inboundId))
                    ->orWhere(function ($fallbackQuery) use ($inbound) {
                        $fallbackQuery
                            ->whereNull('xray_inbound_id')
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
        $updated = $this->configs->update($config, [
            'user_id' => $data->userId,
            'subtitle' => $data->subtitle,
        ]);
        $this->configGroups->assign($updated, $data->groupId);

        return $updated;
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

        return XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
    }
}
