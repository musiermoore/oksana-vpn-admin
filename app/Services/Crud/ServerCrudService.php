<?php

namespace App\Services\Crud;

use App\DTOs\Server\ServerData;
use App\Jobs\InstallWireGuardAgentForServerJob;
use App\Models\Server;
use App\Models\XrayInbound;
use App\Repositories\ServerRepository;
use RuntimeException;

class ServerCrudService
{
    public function __construct(
        private readonly ServerRepository $servers,
    ) {}

    public function create(ServerData $data): Server
    {
        $server = $this->servers->create($data->toServerAttributes());

        $this->dispatchWireGuardInstallIfNeeded($server);

        return $server;
    }

    public function update(Server $server, ServerData $data): Server
    {
        $attributes = $data->toServerAttributes();

        if (blank($attributes['ssh_private_key'])) {
            unset($attributes['ssh_private_key']);
        }

        $previousType = $server->type;
        $updatedServer = $this->servers->update($server, $attributes);

        if ($previousType !== $updatedServer->type) {
            $this->dispatchWireGuardInstallIfNeeded($updatedServer);
        }

        $this->syncXrayInbounds($updatedServer, $data->inbounds);

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
     * @param  array<int, array{id:int, is_active:bool, is_public:bool}>  $inbounds
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
                    'is_active' => (bool) ($payload['is_active'] ?? false),
                    'is_public' => (bool) ($payload['is_public'] ?? false),
                ]);
            });
    }
}
