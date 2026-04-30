<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;

class ServerController extends Controller
{
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
    public function store(Request $request)
    {
        $server = Server::create($this->requestServerData($request));

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
            'server' => $this->serverData($server),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Server $server)
    {
        $data = $this->requestServerData($request);

        if (empty($data['ssh_private_key'])) {
            unset($data['ssh_private_key']);
        }

        $server->update($data);

        return redirect()->back()
            ->with('success', 'Сервер успешно обновлён.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Server $server)
    {
        if ($server->configs()->exists()) {
            return redirect()->back()
                ->with('error', 'К серверу привязаны конфиги.');
        }

        $server->delete();

        return redirect()->route('servers.index')
            ->with('success', 'Сервер успешно удалён');
    }

    private function requestServerData(Request $request): array
    {
        return $request->post();
    }
}
