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
        $proxy = $this->proxies->create($this->buildProxyAttributes($data));
        $proxy->servers()->sync($data->serverIds);

        return $proxy->load(['servers', 'xrayInbound']);
    }

    public function update(Proxy $proxy, ProxyData $data): Proxy
    {
        $updatedProxy = $this->proxies->update($proxy, $this->buildProxyAttributes($data));
        $updatedProxy->servers()->sync($data->serverIds);

        return $updatedProxy->load(['servers', 'xrayInbound']);
    }

    public function delete(Proxy $proxy): void
    {
        $proxy->servers()->detach();
        $this->proxies->delete($proxy);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProxyAttributes(ProxyData $data): array
    {
        return [
            ...$data->toArray(),
            'xray_inbound_id' => $this->resolveXrayInboundId($data->inboundId, $data->serverIds),
        ];
    }

    /**
     * @param  array<int, int>  $serverIds
     */
    private function resolveXrayInboundId(?int $inboundId, array $serverIds): ?int
    {
        if ($inboundId === null || $inboundId < 1 || $serverIds === []) {
            return null;
        }

        $record = XrayInbound::query()
            ->whereIn('server_id', $serverIds)
            ->where('external_id', $inboundId)
            ->orderBy('server_id')
            ->orderBy('id')
            ->first();

        return $record ? (int) $record->getKey() : null;
    }
}
