<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use App\Models\UserToken;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ConfigController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with('configs.user')
            ->withWhereHas('configs', function ($query) {
                $query
                    ->orderBy('server_id')
                    ->orderBy('name');
            })
            ->orderByDesc('deleted_at')
            ->orderBy('created_at')
            ->get();

        return view('configs.index', compact('users'));
    }

    public function create()
    {
        $users = User::get();
        $servers = Server::get();

        $fileNames = Storage::disk('local')->files('configs');

        $existingConfigNames = Config::pluck('name');

        $fileNames = collect($fileNames)
            ->map(function ($fileName) {
                $fileName = str_replace('configs/', '', $fileName);

                return str_replace('.conf', '', $fileName);
            })
            ->filter(function ($fileName) use ($existingConfigNames) {
                return $existingConfigNames->doesntContain($fileName);
            })
            ->sort()
            ->values();

        return view('configs.create', compact('users', 'servers', 'fileNames'));
    }

    public function store(Request $request)
    {
        $user = User::find($request->user_id);

        $configs = $request->post('configs', []);

        $type = 'success';
        $message = 'Конфиги успешно созданы';

        foreach ($configs as $config) {
            $result = $user->createConfig($config);

            if (!$result) {
                $type = 'error';
                $message = 'Некоторые из конфигов не были созданы';
            }
        }

        return redirect()->route('configs.index')
            ->with($type, $message);
    }

    public function edit(Config $config)
    {
        $users = User::get();
        $servers = Server::get();

        return view('configs.edit', compact('config', 'users', 'servers'));
    }

    public function update(Request $request, Config $config)
    {
        $config->update([
            'user_id' => $request->user_id,
            'description' => $request->description
        ]);

        return redirect()->route('configs.index')
            ->with('success', 'Конфиг успешно обновлён');
    }

    public function destroy(Config $config)
    {
        DB::beginTransaction();

        try {
            $config->deleteWgConfig();
            $config->delete();

            DB::commit();
        } catch (Exception) {
            DB::rollBack();

            return redirect()->route('configs.index')
                ->with('success', 'Ошибка при удалении конфига');
        }

        return redirect()->route('configs.index')
            ->with('success', 'Конфиг успешно удалён');
    }

    public function qrCode(Request $request, UserToken $userToken, Config $config)
    {
        if (! $userToken->validateToken($request->password)) {
            abort(404);
        }

        try {
            $configBody = file_get_contents($config->path);

            return QrCode::size(600)->generate($configBody);
        } catch (Exception $exception) {
            abort(500);
        }
    }

    public function download(Request $request, UserToken $userToken, Config $config)
    {
        if (! $userToken->validateToken($request->password)) {
            abort(404);
        }

        try {
            return response()->download($config->path, $config->name . '.conf');
        } catch (Exception $exception) {
            abort(500);
        }
    }
}
