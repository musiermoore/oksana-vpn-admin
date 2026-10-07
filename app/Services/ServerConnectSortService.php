<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Server\ConnectGroupSortData;
use App\DTOs\Server\ServerConnectItemSortData;
use App\Models\Proxy;
use App\Models\Server;
use App\Models\VlessExternalSubscription;
use App\Models\XrayCustomConfig;
use App\Models\XrayInbound;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServerConnectSortService
{
    public function sortGroups(ConnectGroupSortData $data): void
    {
        DB::transaction(function () use ($data): void {
            foreach ($data->items as $item) {
                $type = (string) ($item['type'] ?? '');
                $id = (int) ($item['id'] ?? 0);
                $sortOrder = (int) ($item['sort_order'] ?? 0);

                match ($type) {
                    'server' => $this->updateServerSortOrder($id, $sortOrder),
                    'external_subscription' => $this->updateExternalSubscriptionSortOrder($id, $sortOrder),
                    'xray_custom_config' => $this->updateXrayCustomConfigSortOrder($id, $sortOrder),
                    default => throw new RuntimeException('Неизвестный тип элемента сортировки.'),
                };
            }
        });
    }

    public function sortServerItems(Server $server, ServerConnectItemSortData $data): void
    {
        DB::transaction(function () use ($server, $data): void {
            foreach (array_values($data->items) as $index => $item) {
                $type = (string) ($item['type'] ?? '');
                $id = (int) ($item['id'] ?? 0);

                match ($type) {
                    'inbound' => $this->updateInboundSortOrder($server, $id, $index),
                    'proxy' => $this->updateProxySortOrder($server, $id, $index),
                    default => throw new RuntimeException('Неизвестный тип элемента сортировки сервера.'),
                };
            }
        });
    }

    private function updateServerSortOrder(int $serverId, int $sortOrder): void
    {
        $updated = Server::query()
            ->whereKey($serverId)
            ->update(['sort_order' => $sortOrder]);

        if ($updated === 0) {
            throw new RuntimeException('Сервер для сортировки не найден.');
        }
    }

    private function updateExternalSubscriptionSortOrder(int $subscriptionId, int $sortOrder): void
    {
        $updated = VlessExternalSubscription::query()
            ->whereKey($subscriptionId)
            ->update(['sort_order' => $sortOrder]);

        if ($updated === 0) {
            throw new RuntimeException('Внешняя подписка для сортировки не найдена.');
        }
    }

    private function updateXrayCustomConfigSortOrder(int $configId, int $sortOrder): void
    {
        $updated = XrayCustomConfig::query()
            ->whereKey($configId)
            ->update(['sort_order' => $sortOrder]);

        if ($updated === 0) {
            throw new RuntimeException('Xray custom config для сортировки не найден.');
        }
    }

    private function updateInboundSortOrder(Server $server, int $inboundId, int $sortOrder): void
    {
        $updated = XrayInbound::query()
            ->where('server_id', $server->id)
            ->whereKey($inboundId)
            ->update(['sort_order' => $sortOrder]);

        if ($updated === 0) {
            throw new RuntimeException('Inbound не найден у выбранного сервера.');
        }
    }

    private function updateProxySortOrder(Server $server, int $proxyId, int $sortOrder): void
    {
        $updated = Proxy::query()
            ->where('server_id', $server->id)
            ->whereKey($proxyId)
            ->update(['sort_order' => $sortOrder]);

        if ($updated === 0) {
            throw new RuntimeException('Proxy не найден у выбранного сервера.');
        }
    }
}
