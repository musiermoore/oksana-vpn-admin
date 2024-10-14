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
            ->setStartDate(Carbon::parse($request->start_date))
            ->setEndDate(Carbon::parse($request->end_date));

        $peers = $service->getClientPeers();

        return view('wireguard.traffic', compact('peers', 'users'));
    }
}
