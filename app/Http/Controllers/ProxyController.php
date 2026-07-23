<?php

namespace App\Http\Controllers;

use App\Http\Requests\Proxy\StoreProxyRequest;
use App\Http\Requests\Proxy\UpdateProxyRequest;
use App\Http\Resources\ProxyFormResource;
use App\Http\Resources\ProxyResource;
use App\Models\Proxy;
use App\Models\Server;
use App\Services\Crud\ProxyCrudService;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    public function __construct(
        private readonly ProxyCrudService $proxyService,
    ) {}

    public function index(Request $request)
    {
        $proxies = Proxy::query()
            ->with('xrayInbound:id,external_id')
            ->withCount('servers')
            ->orderBy('id')
            ->get();

        return $this->inertia('Proxies/Index', [
            'proxies' => ProxyResource::collection($proxies)->toArray($request),
        ]);
    }

    public function create()
    {
        return $this->inertia('Proxies/Form', [
            'mode' => 'create',
            'submit_url' => route('proxies.store'),
            'method' => 'post',
            'proxy' => null,
            'server_options' => $this->getServerOptions(),
        ]);
    }

    public function store(StoreProxyRequest $request)
    {
        $proxy = $this->proxyService->create($request->toDto());

        return redirect()->route('proxies.edit', $proxy)
            ->with('success', 'Прокси успешно создан.');
    }

    public function edit(Proxy $proxy)
    {
        $proxy->load(['servers', 'xrayInbound:id,external_id']);

        return $this->inertia('Proxies/Form', [
            'mode' => 'edit',
            'submit_url' => route('proxies.update', $proxy),
            'method' => 'patch',
            'proxy' => (new ProxyFormResource($proxy))->toArray(request()),
            'server_options' => $this->getServerOptions(),
        ]);
    }

    public function update(UpdateProxyRequest $request, Proxy $proxy)
    {
        $this->proxyService->update($proxy, $request->toDto());

        return redirect()->back()
            ->with('success', 'Прокси успешно обновлён.');
    }

    public function destroy(Proxy $proxy)
    {
        $this->proxyService->delete($proxy);

        return redirect()->route('proxies.index')
            ->with('success', 'Прокси успешно удалён.');
    }

    /**
     * @return array<int, array{value:int, label:string}>
     */
    private function getServerOptions(): array
    {
        return Server::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Server $server) => [
                'value' => (int) $server->id,
                'label' => trim(sprintf('%s (%s)', (string) $server->name, (string) $server->code)),
            ])
            ->values()
            ->all();
    }
}
