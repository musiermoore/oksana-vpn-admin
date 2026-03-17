<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use App\Models\UserToken;
use App\Models\VlessConfig;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VlessConfigController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with('vlessConfigs.user')
            ->withWhereHas('vlessConfigs', function ($query) {
                $query
                    ->orderBy('server_id')
                    ->orderBy('name');
            })
            ->orderByDesc('deleted_at')
            ->orderBy('created_at')
            ->get();

        return view('configs.vless', compact('users'));
    }

    public function create()
    {
        $users = User::query()
            ->where('users.is_active', true)
            ->get();
        $servers = Server::get();

        $existingConfigs = VlessConfig::query()
            ->selectRaw('vless_configs.id, CONCAT(servers.code, ": ", vless_configs.name) AS formatted_name')
            ->join('servers', 'servers.id', '=', 'vless_configs.server_id')
            ->whereNull('vless_configs.user_id')
            ->get()
            ->pluck('formatted_name', 'id');

        return view('configs.vless-create', compact('users', 'servers', 'existingConfigs'));
    }

    public function store(Request $request)
    {
        $config = VlessConfig::find($request->config_id);

        if ($config->user_id) {
            return redirect()->back()
                ->with('error', 'Конфиг уже привязан к другому человеку');
        }

        $config->update(['user_id' => $request->user_id]);

        return redirect()->route('vless-configs.index')
            ->with('success', 'Конфиги успешно созданы');
    }

    public function edit(VlessConfig $vlessConfig)
    {
        $config = $vlessConfig;
        $users = User::query()
            ->where('users.is_active', true)
            ->get();

        return view('configs.vless-edit', compact('config', 'users'));
    }

    public function update(Request $request, VlessConfig $vlessConfig)
    {
        $vlessConfig->update([
            'user_id' => $request->user_id,
        ]);

        return redirect()->route('vless-configs.index')
            ->with('success', 'Конфиг успешно обновлён');
    }

    public function destroy(VlessConfig $vlessConfig)
    {
        $vlessConfig->update(['user_id' => null]);

        return redirect()->back()
            ->with('success', 'Конфиг отвязан от пользователя');
    }

    public function qrCode(Request $request, UserToken $userToken, VlessConfig $config)
    {
        if (! $userToken->validateToken($request->password)) {
            abort(404);
        }

        try {
            return QrCode::size(600)->generate($config->getLink());
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            abort(500);
        }
    }

    public function download(Request $request, UserToken $userToken, VlessConfig $config)
    {
        if (! $userToken->validateToken($request->password)) {
            abort(404);
        }

        try {
            return response()->download(
                $config->path,
                preg_replace('/[^a-zA-Z0-9]/', '', $config->name) . '.conf'
            );
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            abort(500);
        }
    }
}
