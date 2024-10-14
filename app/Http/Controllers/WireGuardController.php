<?php

namespace App\Http\Controllers;

use App\Models\Config;
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
        $service = new WireGuardService();
        $peers = $service->sortByActive();

        return view('wireguard.peers', compact('peers'));
    }

    public function traffic(Request $request)
    {
        $users = User::get();

        $service = new WireGuardService();
        $service
            ->setFilter(true)
            ->setUserId($request->user_id)
            ->setStartDate(Carbon::parse($request->query('start_date', now()->subMinutes(10)->format('Y-m-d H:i:s'))))
            ->setEndDate(Carbon::parse($request->query('end_date', now()->format('Y-m-d H:i:s'))));

        $peers = $service->getClientPeers();

        return view('wireguard.traffic', compact('peers', 'users'));
    }
}
