<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Proxy\StoreProxyRequest;
use App\Http\Requests\Proxy\UpdateProxyRequest;
use App\Http\Resources\ProxyFormResource;
use App\Http\Resources\ProxyResource;
use App\Models\Proxy;
use App\Models\Server;
use App\Models\XrayInbound;
use App\Services\Crud\ProxyCrudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProxyController extends Controller
{
    public function __construct(
        private readonly ProxyCrudService $proxyService,
    ) {}

    public function index(Request $request)
    {
        $proxies = Proxy::query()
            ->with(['server:id,name', 'xrayInbound:id,external_id', 'hosts'])
            ->orderBy('server_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->inertia('Proxies/Index', [
            'proxies' => ProxyResource::collection($proxies)->toArray($request),
        ]);
    }

    public function create(Request $request)
    {
        $sourceProxy = $request->integer('duplicate') > 0
            ? Proxy::query()->with(['server', 'xrayInbound:id,external_id', 'hosts'])->findOrFail($request->integer('duplicate'))
            : null;

        return $this->inertia('Proxies/Form', [
            'mode' => 'create',
            'submit_url' => route('proxies.store'),
            'method' => 'post',
            'proxy' => $sourceProxy ? (new ProxyFormResource($sourceProxy))->toArray($request) : null,
            'server_options' => $this->getServerOptions(),
            'inbound_options_by_server' => $this->getInboundOptionsByServer(),
        ]);
    }

    public function duplicate(Proxy $proxy): RedirectResponse
    {
        return redirect()->route('proxies.create', ['duplicate' => $proxy->getKey()]);
    }

    public function store(StoreProxyRequest $request)
    {
        $proxy = $this->proxyService->create($request->toDto());

        return redirect()->route('proxies.edit', $proxy)
            ->with('success', 'Прокси успешно создан.');
    }

    public function edit(Proxy $proxy)
    {
        $proxy->load(['server', 'xrayInbound:id,external_id', 'hosts']);

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
            ->selectRaw("
                xray_inbounds.id,
                xray_inbounds.server_id,
                xray_inbounds.external_id,
                xray_inbounds.sort_order,
                JSON_UNQUOTE(JSON_EXTRACT(xray_inbounds.params, '$.remark')) as label,
                JSON_UNQUOTE(JSON_EXTRACT(xray_inbounds.params, '$.protocol')) as protocol_name,
                JSON_UNQUOTE(JSON_EXTRACT(xray_inbounds.params, '$.port')) as inbound_port
            ")
            ->orderBy('xray_inbounds.server_id')
            ->orderBy('xray_inbounds.sort_order')
            ->orderBy('xray_inbounds.external_id')
            ->orderBy('xray_inbounds.id')
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
        $remark = trim((string) ($inbound->label ?? data_get($inbound->params, 'remark', '')));
        $protocol = trim((string) ($inbound->protocol_name ?? ''));
        $port = $inbound->inbound_port ?? null;
        $label = implode(' · ', array_filter([$remark, $protocol, is_numeric($port) ? (string) $port : '']));

        if ($label !== '') {
            return sprintf('%s (Inbound #%d)', $label, (int) $inbound->external_id);
        }

        return sprintf('Inbound #%d', (int) $inbound->external_id);
    }
}
