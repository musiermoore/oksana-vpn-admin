<?php

namespace App\Repositories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Collection;

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

    public function findOrFail(int $id): Server
    {
        return Server::query()->findOrFail($id);
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Server>
     */
    public function findByIds(array $ids): Collection
    {
        return Server::query()
            ->whereIn('id', $ids)
            ->get();
    }
}
