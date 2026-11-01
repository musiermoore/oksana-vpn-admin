<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Proxy\ProxyGroupData;
use App\Http\Requests\Proxy\StoreProxyGroupRequest;
use App\Models\Proxy;
use App\Models\ProxyGroup;
use App\Services\ProxyGroupConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ProxyGroupController extends Controller
{
    public function __construct(private readonly ProxyGroupConfigurationService $configuration) {}

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $groups = ProxyGroup::query()
            ->with(['proxies.server:id,name,ip,link_host', 'proxies.xrayInbound:id,params', 'proxies.hosts'])
            ->withCount('proxies')
            ->orderBy('name')
            ->get()
            ->map(fn (ProxyGroup $group): array => [
                'id' => (int) $group->id,
                'name' => $group->name,
                'proxy_count' => (int) $group->proxies_count,
                'proxies' => $group->proxies->map(function (Proxy $proxy): array {
                    $chain = $proxy->hosts->where('is_active', true)->sortBy('sort')->values()
                        ->map(fn ($host): array => [
                            'host' => $host->host,
                            'port' => (int) $host->port,
                        ]);
                    $destinationHost = $proxy->server?->getLinkAddressHost();
                    $destinationPort = data_get($proxy->xrayInbound?->params, 'port');

                    if ($destinationHost !== null && $destinationHost !== '' && is_numeric($destinationPort)) {
                        $chain->push([
                            'host' => $destinationHost,
                            'port' => (int) $destinationPort,
                        ]);
                    }

                    return [
                        'id' => (int) $proxy->id,
                        'name' => $proxy->name,
                        'server_name' => $proxy->server?->name,
                        'is_ready' => (bool) $proxy->is_ready,
                        'chain' => $chain->all(),
                    ];
                })->all(),
            ])->all();

        return $this->inertia('ProxyGroups/Index', ['groups' => $groups]);
    }

    public function store(StoreProxyGroupRequest $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        /** @var ProxyGroupData $data */
        $data = $request->toData();
        ProxyGroup::query()->create($data->toArray());

        return redirect()->route('proxy-groups.index')->with('success', 'Группа прокси создана.');
    }

    public function update(StoreProxyGroupRequest $request, ProxyGroup $proxyGroup): RedirectResponse
    {
        $this->authorizeAdmin($request);
        /** @var ProxyGroupData $data */
        $data = $request->toData();
        $proxyGroup->update($data->toArray());

        return redirect()->route('proxy-groups.index')->with('success', 'Группа прокси обновлена.');
    }

    public function destroy(Request $request, ProxyGroup $proxyGroup): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $proxyGroup->delete();

        return redirect()->route('proxy-groups.index')->with('success', 'Группа прокси удалена.');
    }

    public function preview(Request $request, ProxyGroup $proxyGroup): JsonResponse
    {
        $this->authorizeAdmin($request);
        try {
            return response()->json($this->configuration->preview($proxyGroup));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function apply(Request $request, ProxyGroup $proxyGroup): JsonResponse
    {
        $this->authorizeAdmin($request);
        try {
            return response()->json($this->configuration->apply($proxyGroup));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось применить конфигурацию.',
                'steps' => [[
                    'server' => 'Приложение',
                    'step' => 'Подготовка конфигурации',
                    'success' => false,
                    'message' => $exception->getMessage(),
                ]],
            ], 422);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
    }
}
