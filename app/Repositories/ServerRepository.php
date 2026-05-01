<?php

namespace App\Repositories;

use App\Models\Server;

class ServerRepository
{
    public function create(array $attributes): Server
    {
        return Server::create($attributes);
    }

    public function update(Server $server, array $attributes): Server
    {
        $server->update($attributes);

        return $server->refresh();
    }

    public function delete(Server $server): void
    {
        $server->delete();
    }
}
