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
use Log;
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

        return $this->inertia('Configs/Index', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'is_active' => $user->is_active,
                'edit_url' => route('users.edit', $user),
                'configs' => $user->configs->map(fn (Config $config) => $this->configData($config))->values(),
            ])->values(),
            'tabs' => [
                ['label' => 'WireGuard', 'href' => route('configs.index'), 'active' => true],
                ['label' => 'VLESS', 'href' => route('vless-configs.index'), 'active' => false],
            ],
        ]);
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

        return $this->inertia('Configs/Create', [
            'submit_url' => route('configs.store'),
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
            'servers' => $servers->map(fn (Server $server) => $this->serverData($server))->values(),
            'file_names' => $fileNames->values(),
        ]);
    }

    public function store(Request $request)
    {
        $user = User::find($request->user_id);

        $configs = $request->post('configs', []);

        $success = true;

        foreach ($configs as $config) {
            $success = $user->createConfig($config);
        }

        if (!$success) {
            return redirect()->back()
                ->with('error', 'Некоторые из конфигов не были созданы');
        }

        return redirect()->route('configs.index')
            ->with('success', 'Конфиги успешно созданы');
    }

    public function createBulk()
    {
        $servers = Server::get();

        return $this->inertia('Configs/BulkCreate', [
            'submit_url' => route('configs.store-bulk'),
            'servers' => $servers->map(fn (Server $server) => $this->serverData($server))->values(),
        ]);
    }

    public function storeBulk(Request $request)
    {
        $server = Server::find($request->server_id);

        $users = User::query()
            ->with('configs')
            ->whereDoesntHave('configs', function ($query) use ($server) {
                $query->where('server_id', '=', $server->id);
            })
            ->get();

        $errorConfigs = [];

        foreach ($users as $user) {
            $telegram = str_replace('@', '', $user->telegram);

            $config = [
                'name' => $telegram . '_' . $server->code,
                'server_id' => $server->id,
            ];

            $success = $user->createConfig($config);

            if (!$success) {
                $errorConfigs[] = $config['name'];
            }
        }

        if ($errorConfigs) {
            return redirect()->back()
                ->with('error', 'Некоторые из конфигов не были созданы: ' . implode(', ', $errorConfigs));
        }

        return redirect()->route('configs.index')
            ->with('success', 'Конфиги успешно созданы');
    }

    public function edit(Config $config)
    {
        $users = User::get();
        $servers = Server::get();

        return $this->inertia('Configs/Edit', [
            'submit_url' => route('configs.update', $config),
            'config' => $this->configData($config),
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
            'servers' => $servers->map(fn (Server $server) => $this->serverData($server))->values(),
        ]);
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
        try {
            if (! $config->deleteWgConfig()) {
                return redirect()->route('configs.index')
                    ->with('error', 'Ошибка при удалении конфига');
            }

            $config->delete();
        } catch (Exception) {
            return redirect()->route('configs.index')
                ->with('error', 'Ошибка при удалении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно удалён');
    }

    public function enable(Config $config)
    {
        try {
            if (! $config->enableWgConfig()) {
                return redirect()->route('configs.index')
                    ->with('error', 'Ошибка при включении конфига');
            }

            $config->update(['is_active' => true]);
        } catch (Exception) {
            return redirect()->route('configs.index')
                ->with('error', 'Ошибка при включении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно включён');
    }

    public function disable(Config $config)
    {
        try {
            if (! $config->disableWgConfig()) {
                return redirect()->route('configs.index')
                    ->with('error', 'Ошибка при отключении конфига');
            }

            $config->update(['is_active' => false]);
        } catch (Exception) {
            return redirect()->route('configs.index')
                ->with('error', 'Ошибка при отключении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно отключён');
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
            Log::error($exception->getMessage());
            abort(500);
        }
    }

    public function download(Request $request, UserToken $userToken, Config $config)
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
