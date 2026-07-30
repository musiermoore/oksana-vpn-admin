<?php

namespace App\Services\Crud;

use App\DTOs\Proxy\ProxyData;
use App\Models\Proxy;
use App\Models\XrayInbound;
use App\Repositories\ProxyRepository;

class ProxyCrudService
{
    public function __construct(
        private readonly ProxyRepository $proxies,
    ) {}

    public function create(ProxyData $data): Proxy
    {
        $proxy = $this->proxies->create([
            ...$this->buildProxyAttributes($data),
            'sort_order' => $this->resolveNextSortOrder($data->serverId),
        ]);

        return $proxy->load(['server', 'xrayInbound']);
    }

    public function update(Proxy $proxy, ProxyData $data): Proxy
    {
        $updatedProxy = $this->proxies->update($proxy, $this->buildProxyAttributes($data));

        if ((int) $proxy->server_id !== (int) $data->serverId) {
            $updatedProxy->update([
                'sort_order' => $this->resolveNextSortOrder($data->serverId),
            ]);
        }

        return $updatedProxy->load(['server', 'xrayInbound']);
    }

    public function delete(Proxy $proxy): void
    {
        $this->proxies->delete($proxy);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProxyAttributes(ProxyData $data): array
    {
        return [
            ...$data->toArray(),
            'xray_inbound_id' => $this->resolveXrayInboundId($data->inboundId, $data->serverId),
        ];
    }

    private function resolveXrayInboundId(?int $inboundId, int $serverId): ?int
    {
        if ($inboundId === null || $inboundId < 1 || $serverId < 1) {
            return null;
        }

        $record = XrayInbound::query()
            ->where('server_id', $serverId)
            ->where('external_id', $inboundId)
            ->orderBy('id')
            ->first();

        return $record ? (int) $record->getKey() : null;
    }

    private function resolveNextSortOrder(int $serverId): int
    {
        $maxSortOrder = Proxy::query()
            ->where('server_id', $serverId)
            ->max('sort_order');

        return is_numeric($maxSortOrder) ? ((int) $maxSortOrder + 1) : 0;
    }
}
