<?php

namespace App\Services\Crud;

use App\Models\Server;
use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Repositories\ShadowsocksConfigRepository;
use App\Services\XuiConfigServiceFactory;
use RuntimeException;

class ShadowsocksConfigCrudService
{
    public function __construct(
        private readonly ShadowsocksConfigRepository $configs,
    ) {}

    public function create(int $userId, int $serverId, int $inboundId): ShadowsocksConfig
    {
        $user = User::query()->find($userId);
        $server = Server::query()->find($serverId);

        if (! $user) {
            throw new RuntimeException('Пользователь не найден');
        }

        if (! $server || ! $server->isVlessType() || ! $server->is_active) {
            throw new RuntimeException('Xray-сервер не найден');
        }

        $xui = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
        $inbound = collect($xui->getAllShadowsocksInbounds())
            ->first(fn (array $row) => (int) ($row['id'] ?? 0) === $inboundId);

        if (! $inbound) {
            throw new RuntimeException('Выбранный Shadowsocks-вход не найден');
        }

        $existingConfig = $user->shadowsocksConfigs()
            ->where('server_id', $server->id)
            ->where('inbound_id', $inboundId)
            ->first();

        if ($existingConfig) {
            throw new RuntimeException('Для этого входа у пользователя уже есть Shadowsocks-конфиг');
        }

        return $xui->createShadowsocksClientOnAnyInboundId($user, $inboundId);
    }

    public function update(ShadowsocksConfig $config, int $userId): ShadowsocksConfig
    {
        return $this->configs->update($config, ['user_id' => $userId]);
    }

    public function unassign(ShadowsocksConfig $config): ShadowsocksConfig
    {
        return $this->configs->update($config, ['user_id' => null]);
    }
}
