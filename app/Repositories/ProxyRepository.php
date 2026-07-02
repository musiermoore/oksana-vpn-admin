<?php

namespace App\Repositories;

use App\Models\Proxy;

class ProxyRepository
{
    public function create(array $attributes): Proxy
    {
        return Proxy::query()->create($attributes);
    }

    public function update(Proxy $proxy, array $attributes): Proxy
    {
        $proxy->update($attributes);

        return $proxy->refresh();
    }

    public function delete(Proxy $proxy): void
    {
        $proxy->delete();
    }
}
