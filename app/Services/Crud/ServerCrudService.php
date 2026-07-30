<?php

namespace App\Services\Crud;

use App\DTOs\Server\ServerData;
use App\Jobs\InstallWireGuardAgentForServerJob;
use App\Models\Server;
use App\Models\ServerPrice;
use App\Models\XrayInbound;
use App\Repositories\ServerRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServerCrudService
{
    public function __construct(
        private readonly ServerRepository $servers,
    ) {}

    public function create(ServerData $data): Server
    {
        $server = DB::transaction(function () use ($data): Server {
            $server = $this->servers->create([
                ...$data->toServerAttributes(),
                'sort_order' => $this->resolveNextServerSortOrder(),
            ]);

            $this->syncPrices($server, $data->prices);

            return $server;
        });

        $this->dispatchWireGuardInstallIfNeeded($server);

        return $server;
    }

    public function update(Server $server, ServerData $data): Server
    {
        $previousType = $server->type;

        $updatedServer = DB::transaction(function () use ($server, $data): Server {
            $attributes = $data->toServerAttributes();

            if (blank($attributes['ssh_private_key'])) {
                unset($attributes['ssh_private_key']);
            }

            $updatedServer = $this->servers->update($server, $attributes);
            $this->syncPrices($updatedServer, $data->prices);
            $this->syncXrayInbounds($updatedServer, $data->inbounds);

            return $updatedServer;
        });

        if ($previousType !== $updatedServer->type) {
            $this->dispatchWireGuardInstallIfNeeded($updatedServer);
        }

        return $updatedServer;
    }

    public function delete(Server $server): void
    {
        if ($server->configs()->exists()) {
            throw new RuntimeException('К серверу привязаны конфиги.');
        }

        $this->servers->delete($server);
    }

    public function enable(Server $server): Server
    {
        return $this->servers->update($server, [
            'is_active' => true,
        ]);
    }

    public function disable(Server $server): Server
    {
        return $this->servers->update($server, [
            'is_active' => false,
        ]);
    }

    private function dispatchWireGuardInstallIfNeeded(Server $server): void
    {
        if (! $server->isModernWireGuardType()) {
            return;
        }

        InstallWireGuardAgentForServerJob::dispatch($server->id);
    }

    /**
     * @param  array<int, array{id:int, sort_order?:int, is_active:bool, is_public:bool}>  $inbounds
     */
    private function syncXrayInbounds(Server $server, array $inbounds): void
    {
        if ($inbounds === []) {
            return;
        }

        $payloadById = collect($inbounds)
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']))
            ->keyBy(fn (array $item): int => (int) $item['id']);

        if ($payloadById->isEmpty()) {
            return;
        }

        XrayInbound::query()
            ->where('server_id', $server->id)
            ->whereIn('id', $payloadById->keys()->all())
            ->get()
            ->each(function (XrayInbound $inbound) use ($payloadById): void {
                $payload = $payloadById->get((int) $inbound->getKey());

                if (! is_array($payload)) {
                    return;
                }

                $inbound->update([
                    'sort_order' => (int) ($payload['sort_order'] ?? $inbound->sort_order ?? 0),
                    'is_active' => (bool) ($payload['is_active'] ?? false),
                    'is_public' => (bool) ($payload['is_public'] ?? false),
                ]);
            });
    }

    private function resolveNextServerSortOrder(): int
    {
        $maxSortOrder = Server::query()->max('sort_order');

        return is_numeric($maxSortOrder) ? ((int) $maxSortOrder + 1) : 0;
    }

    /**
     * @param  array<int, array{id?:int, effective_from:string, price:int|float|string}>  $prices
     */
    private function syncPrices(Server $server, array $prices): void
    {
        $normalizedPrices = collect($prices)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values();

        $keepIds = $normalizedPrices
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $server->prices()
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->when($keepIds === [], fn ($query) => $query)
            ->delete();

        $normalizedPrices->each(function (array $row) use ($server): void {
            $attributes = [
                'effective_from' => (string) $row['effective_from'],
                'price' => (float) $row['price'],
            ];

            $priceId = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;

            if ($priceId !== null && $priceId > 0) {
                $server->prices()
                    ->whereKey($priceId)
                    ->update($attributes);

                return;
            }

            $server->prices()->create($attributes);
        });
    }
}
