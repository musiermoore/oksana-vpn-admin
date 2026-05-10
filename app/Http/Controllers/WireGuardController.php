<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServerResource;
use App\Http\Resources\UserResource;
use App\Models\Config;
use App\Models\Server;
use App\Models\Traffic;
use App\Models\User;
use App\Services\WireGuardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WireGuardController extends Controller
{
    public function activePeers(Request $request)
    {
        $servers = Server::get();

        $selectedServerId = (int)$request->query('server_id', $servers->value('id'));

        $service = new WireGuardService();
        $peers = $service->sortByActive($selectedServerId);

        return $this->inertia('WireGuard/ActivePeers', [
            'filters' => [
                'server_id' => $selectedServerId,
            ],
            'servers' => ServerResource::collection($servers)->toArray($request),
            'peerGroups' => collect($peers)->map(function (Collection $peerType, string $key) {
                return [
                    'key' => $key,
                    'label' => $key === 'active' ? 'Активные' : 'Оффлайн',
                    'items' => collect($peerType)->map(function (array $peer) {
                        return [
                            'telegram' => $peer['telegram'] ?? null,
                            'latest_handshake' => $peer['latest_handshake'] ?: '-',
                            'transfer' => $peer['transfer'] ?: '-',
                            'formatted_last_traffic' => $peer['config']->formatted_last_traffic ?? [],
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    public function traffic(Request $request)
    {
        $users = User::get();
        $servers = Server::get();
        $selectedServerId = (int)$request->query('server_id', $servers->value('id'));

        $service = new WireGuardService();
        $service
            ->setFilter(true)
            ->setUserId($request->user_id)
            ->setStartDate(Carbon::parse($request->query('start_date', now()->subMinutes(10))))
            ->setEndDate(Carbon::parse($request->query('end_date', now())));

        $peers = $service->getClientPeers($selectedServerId);

        return $this->inertia('WireGuard/Traffic', [
            'filters' => [
                'server_id' => $selectedServerId,
                'user_id' => $request->user_id,
                'start_date' => $request->query('start_date', now()->subMinutes(10)->format('Y-m-d\TH:i')),
                'end_date' => $request->query('end_date', now()->format('Y-m-d\TH:i')),
            ],
            'server_time' => now()->format('Y-m-d\TH:i'),
            'servers' => ServerResource::collection($servers)->toArray($request),
            'users' => UserResource::collection($users)->toArray($request),
            'peers' => collect($peers)->map(function (array $peer) {
                return [
                    'telegram' => $peer['telegram'] ?? null,
                    'name' => $peer['name'] ?? null,
                    'formatted_last_traffic' => $peer['config']->formatted_last_traffic ?? [],
                ];
            })->values(),
        ]);
    }
}
