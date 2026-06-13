<?php

namespace App\Services\Crud;

use App\DTOs\Server\ServerData;
use App\Jobs\InstallWireGuardAgentForServerJob;
use App\Models\Server;
use App\Repositories\ServerRepository;
use RuntimeException;

class ServerCrudService
{
    public function __construct(
        private readonly ServerRepository $servers,
    ) {}

    public function create(ServerData $data): Server
    {
        $server = $this->servers->create($data->toArray());

        $this->dispatchWireGuardInstallIfNeeded($server);

        return $server;
    }

    public function update(Server $server, ServerData $data): Server
    {
        $attributes = $data->toArray();

        if (blank($attributes['ssh_private_key'])) {
            unset($attributes['ssh_private_key']);
        }

        $previousType = $server->type;
        $updatedServer = $this->servers->update($server, $attributes);

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

    private function dispatchWireGuardInstallIfNeeded(Server $server): void
    {
        if (! $server->isModernWireGuardType()) {
            return;
        }

        InstallWireGuardAgentForServerJob::dispatch($server->id);
    }
}
