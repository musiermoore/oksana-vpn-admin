<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Server;
use App\Models\Traffic;
use App\Models\User;
use App\Services\WireGuardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WireGuardController extends Controller
{
    public function activePeers(Request $request)
    {
        $servers = Server::get();

        $selectedServerId = (int) $request->all('server_id', $servers->value('id'));

        $service = new WireGuardService();
        $peers = $service->sortByActive($selectedServerId);

        return view('wireguard.peers', compact('peers', 'servers'));
    }

    public function traffic(Request $request)
    {
        $users = User::get();
        $servers = Server::get();
        $selectedServerId = (int) $request->all('server_id', $servers->value('id'));

        $service = new WireGuardService();
        $service
            ->setFilter(true)
            ->setUserId($request->user_id)
            ->setStartDate(Carbon::parse($request->query('start_date', now()->subMinutes(10))))
            ->setEndDate(Carbon::parse($request->query('end_date', now())));

        $peers = $service->getClientPeers($selectedServerId);

        return view('wireguard.traffic', compact('peers', 'users', 'servers'));
    }
}
