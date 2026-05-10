<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsInertiaData;
use App\Http\Requests\Server\StoreServerRequest;
use App\Http\Requests\Server\UpdateServerRequest;
use App\Models\Server;
use App\Services\Crud\ServerCrudService;
use RuntimeException;

class ServerController extends Controller
{
    use BuildsInertiaData;

    public function __construct(
        private readonly ServerCrudService $serverService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $servers = Server::all();

        return $this->inertia('Servers/Index', [
            'servers' => $servers->map(fn (Server $server) => $this->serverData($server))->values(),
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
        return $this->inertia('Servers/Form', [
            'mode' => 'edit',
            'submit_url' => route('servers.update', $server),
            'method' => 'patch',
            'server' => $this->serverData($server, true),
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
}
