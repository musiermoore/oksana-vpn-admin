<?php

declare(strict_types=1);

namespace App\Services\Crud;

use App\DTOs\Proxy\ProxyData;
use App\DTOs\Proxy\ProxyHostData;
use App\Models\Proxy;
use App\Models\XrayInbound;
use App\Repositories\ProxyRepository;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\DB;

class ProxyCrudService
{
    public function __construct(
        private readonly ProxyRepository $proxies,
    ) {}

    public function create(ProxyData $data): Proxy
    {
        $proxy = DB::transaction(function () use ($data): Proxy {
            $proxy = $this->proxies->create([
                ...$this->buildProxyAttributes($data),
                'sort_order' => $this->resolveNextSortOrder($data->serverId),
            ]);

            $this->syncHosts($proxy, $data->hosts);

            return $proxy;
        });

        return $proxy->load(['server', 'xrayInbound', 'hosts']);
    }

    public function update(Proxy $proxy, ProxyData $data): Proxy
    {
        $serverChanged = (int) $proxy->server_id !== $data->serverId;
        $updatedProxy = DB::transaction(function () use ($proxy, $data, $serverChanged): Proxy {
            $updatedProxy = $this->proxies->update($proxy, $this->buildProxyAttributes($data));
            $this->syncHosts($updatedProxy, $data->hosts);

            if ($serverChanged) {
                $updatedProxy->update([
                    'sort_order' => $this->resolveNextSortOrder($data->serverId),
                ]);
            }

            return $updatedProxy;
        });

        return $updatedProxy->load(['server', 'xrayInbound', 'hosts']);
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
        $attributes = $data->toArray();
        unset($attributes['hosts']);

        return [
            ...$attributes,
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

    private function syncHosts(Proxy $proxy, DataCollection $hosts): void
    {
        $existingIds = $proxy->hosts()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $retainedIds = [];

        foreach ($hosts->items() as $host) {
            if (! $host instanceof ProxyHostData) {
                continue;
            }

            $attributes = [
                'host' => trim($host->host),
                'port' => $host->port,
                'sort' => $host->sort,
                'is_active' => $host->isActive,
            ];

            if ($host->id !== null && in_array($host->id, $existingIds, true)) {
                $proxy->hosts()->whereKey($host->id)->update($attributes);
                $retainedIds[] = $host->id;

                continue;
            }

            $retainedIds[] = (int) $proxy->hosts()->create($attributes)->getKey();
        }

        $proxy->hosts()
            ->when($retainedIds !== [], fn ($query) => $query->whereNotIn('id', $retainedIds))
            ->delete();
    }

    private function resolveNextSortOrder(int $serverId): int
    {
        $maxSortOrder = Proxy::query()
            ->where('server_id', $serverId)
            ->max('sort_order');

        return is_numeric($maxSortOrder) ? ((int) $maxSortOrder + 1) : 0;
    }
}
