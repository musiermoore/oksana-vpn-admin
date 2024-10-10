<?php

namespace App\Http\Controllers;

use App\Services\WireGuardService;
use Illuminate\Http\Request;

class WireGuardController extends Controller
{
    public function activePeers()
    {
        $service = new WireGuardService();
        $peers = $service->sortByActive();

        return view('wireguard.peers', compact('peers'));
    }
}
