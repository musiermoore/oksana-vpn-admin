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

        return view('servers.index', compact('servers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('servers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $server = Server::create($request->post());

        return redirect()->route('servers.edit', $server->id)
            ->with('success', 'Сервер успешно создан.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Server $server)
    {
        return view('servers.edit', compact('server'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Server $server)
    {
        $server->update($request->post());

        return redirect()->back()
            ->with('success', 'Сервер успешно создан.');

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Server $server)
    {
        if ($server->has('configs')) {
            return redirect()->back()
                ->with('error', 'К серверу привязаны конфиги.');
        }

        $server->delete();

        return redirect()->route('servers.index')
            ->with('success', 'Сервер успешно удалён');
    }
}
