<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerFormResource;
use App\Http\Resources\ServerResource;
use App\Http\Resources\VlessExternalSubscriptionResource;
use App\Http\Requests\Server\SortConnectGroupsRequest;
use App\Http\Requests\Server\SortServerConnectItemsRequest;
use App\Http\Requests\Server\StoreServerRequest;
use App\Http\Requests\Server\UpdateServerRequest;
use App\Models\Server;
use App\Models\VlessExternalSubscription;
use App\Services\Crud\ServerCrudService;
use App\Services\ServerConnectSortService;
use Illuminate\Http\Request;
use RuntimeException;

class ServerController extends Controller
{
    public function __construct(
        private readonly ServerCrudService $serverService,
        private readonly ServerConnectSortService $sortService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $servers = Server::query()->ordered()->get();
        $externalSubscriptions = VlessExternalSubscription::query()->ordered()->get();
        $connectSortItems = collect([
            ...collect(ServerResource::collection($servers)->toArray($request))
                ->map(fn (array $server) => [
                    'id' => (int) $server['id'],
                    'type' => 'server',
                    'sort_order' => (int) $server['sort_order'],
                    'name' => (string) $server['name'],
                    'code' => (string) $server['code'],
                    'label' => 'Сервер',
                ])
                ->all(),
            ...collect(VlessExternalSubscriptionResource::collection($externalSubscriptions)->toArray($request))
                ->map(fn (array $subscription) => [
                    'id' => (int) $subscription['id'],
                    'type' => 'external_subscription',
                    'sort_order' => (int) $subscription['sort_order'],
                    'name' => (string) $subscription['name'],
                    'code' => 'EXT',
                    'label' => 'Внешняя подписка',
                ])
                ->all(),
        ])
            ->sortBy([
                fn (array $item) => (int) $item['sort_order'],
                fn (array $item) => (string) $item['type'],
                fn (array $item) => (int) $item['id'],
            ])
            ->values()
            ->all();

        return $this->inertia('Servers/Index', [
            'servers' => ServerResource::collection($servers)->toArray($request),
            'connect_sort_items' => $connectSortItems,
            'sort_connect_groups_url' => route('servers.sort-connect-groups'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return $this->inertia('Servers/Form', [
            'mode' => 'create',
            'submit_url' => route('servers.store'),
            'method' => 'post',
            'server' => null,
            'sort_connect_items_url' => null,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServerRequest $request)
    {
        $server = $this->serverService->create($request->toDto());

        return redirect()->route('servers.edit', $server->id)
            ->with('success', 'Сервер успешно создан.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Server $server)
    {
        $server->load([
            'prices',
            'xrayInbounds',
            'proxies',
        ]);

        return $this->inertia('Servers/Form', [
            'mode' => 'edit',
            'submit_url' => route('servers.update', $server),
            'method' => 'patch',
            'server' => (new ServerFormResource($server))->toArray(request()),
            'sort_connect_items_url' => route('servers.sort-connect-items', $server),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServerRequest $request, Server $server)
    {
        $this->serverService->update($server, $request->toDto());

        return redirect()->back()
            ->with('success', 'Сервер успешно обновлён.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Server $server)
    {
        try {
            $this->serverService->delete($server);
        } catch (RuntimeException $exception) {
            return redirect()->back()
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('servers.index')
            ->with('success', 'Сервер успешно удалён');
    }

    public function enable(Server $server)
    {
        $this->serverService->enable($server);

        return redirect()->back()
            ->with('success', 'Сервер успешно включён.');
    }

    public function disable(Server $server)
    {
        $this->serverService->disable($server);

        return redirect()->back()
            ->with('success', 'Сервер успешно отключён.');
    }

    public function sortConnectGroups(SortConnectGroupsRequest $request)
    {
        $this->sortService->sortGroups($request->toDto());

        return redirect()->back()
            ->with('success', 'Порядок connect-групп обновлён.');
    }

    public function sortConnectItems(SortServerConnectItemsRequest $request, Server $server)
    {
        $this->sortService->sortServerItems($server, $request->toDto());

        return redirect()->back()
            ->with('success', 'Порядок connect-элементов обновлён.');
    }
}
