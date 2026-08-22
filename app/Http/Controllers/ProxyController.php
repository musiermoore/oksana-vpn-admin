<?php

namespace App\Http\Controllers;

use App\Http\Requests\Proxy\StoreProxyRequest;
use App\Http\Requests\Proxy\UpdateProxyRequest;
use App\Http\Resources\ProxyFormResource;
use App\Http\Resources\ProxyResource;
use App\Models\Proxy;
use App\Models\Server;
use App\Models\XrayInbound;
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
            ->with(['server:id,name', 'xrayInbound:id,external_id'])
            ->orderBy('server_id')
            ->orderBy('sort_order')
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
            'inbound_options_by_server' => $this->getInboundOptionsByServer(),
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
        $proxy->load(['server', 'xrayInbound:id,external_id']);

        return $this->inertia('Proxies/Form', [
            'mode' => 'edit',
            'submit_url' => route('proxies.update', $proxy),
            'method' => 'patch',
            'proxy' => (new ProxyFormResource($proxy))->toArray(request()),
            'server_options' => $this->getServerOptions(),
            'inbound_options_by_server' => $this->getInboundOptionsByServer(),
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
            ->ordered()
            ->get()
            ->map(fn (Server $server) => [
                'value' => (int) $server->id,
                'label' => trim(sprintf('%s (%s)', (string) $server->name, (string) $server->code)),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, array{value:int, label:string}>>
     */
    private function getInboundOptionsByServer(): array
    {
        return XrayInbound::query()
            ->with('server:id')
            ->ordered()
            ->get()
            ->groupBy(fn (XrayInbound $inbound) => (int) $inbound->server_id)
            ->map(fn ($inbounds) => $inbounds
                ->map(fn (XrayInbound $inbound) => [
                    'value' => (int) $inbound->external_id,
                    'label' => $this->buildInboundLabel($inbound),
                ])
                ->values()
                ->all())
            ->all();
    }

    private function buildInboundLabel(XrayInbound $inbound): string
    {
        $remark = trim((string) data_get($inbound->params, 'remark', ''));

        if ($remark !== '') {
            return sprintf('Inbound #%d - %s', (int) $inbound->external_id, $remark);
        }

        return sprintf('Inbound #%d', (int) $inbound->external_id);
    }
}
