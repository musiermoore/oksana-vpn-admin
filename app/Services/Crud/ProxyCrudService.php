<?php

namespace App\Services\Crud;

use App\DTOs\Proxy\ProxyData;
use App\Models\Proxy;
use App\Repositories\ProxyRepository;

class ProxyCrudService
{
    public function __construct(
        private readonly ProxyRepository $proxies,
    ) {}

    public function create(ProxyData $data): Proxy
    {
        $proxy = $this->proxies->create($data->toArray());
        $proxy->servers()->sync($data->serverIds);

        return $proxy->load('servers');
    }

    public function update(Proxy $proxy, ProxyData $data): Proxy
    {
        $updatedProxy = $this->proxies->update($proxy, $data->toArray());
        $updatedProxy->servers()->sync($data->serverIds);

        return $updatedProxy->load('servers');
    }

    public function delete(Proxy $proxy): void
    {
        $proxy->servers()->detach();
        $this->proxies->delete($proxy);
    }
}
