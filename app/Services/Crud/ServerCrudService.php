<?php

namespace App\Services\Crud;

use App\DTOs\Server\ServerData;
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
        return $this->servers->create($data->toArray());
    }

    public function update(Server $server, ServerData $data): Server
    {
        $attributes = $data->toArray();

        if (blank($attributes['ssh_private_key'])) {
            unset($attributes['ssh_private_key']);
        }

        return $this->servers->update($server, $attributes);
    }

    public function delete(Server $server): void
    {
        if ($server->configs()->exists()) {
            throw new RuntimeException('К серверу привязаны конфиги.');
        }

        $this->servers->delete($server);
    }
}
