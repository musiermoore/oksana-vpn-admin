<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Traffic;
use App\Services\WireGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WireGuardController extends Controller
{
    public function activePeers()
    {
        $service = new WireGuardService();
        $peers = $service->sortByActive();

        return view('wireguard.peers', compact('peers'));
    }
}
