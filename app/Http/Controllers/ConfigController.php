<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\Process\Process;

class ConfigController extends Controller
{
    public function index()
    {
        $users = User::with('configs')
            ->orderByDesc('deleted_at')
            ->orderBy('created_at')
            ->get();
        return view('configs.index', compact('users'));
    }

    public function create()
    {
        $users = User::get();

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

        return view('configs.create', compact('users', 'fileNames'));
    }

    public function store(Request $request)
    {
        $user = User::find($request->user_id);

        $configs = $request->post('configs', []);

        foreach ($configs as $config) {
            $user->configs()->create($config);

            $path = storage_path('create-wg-config.sh');
            $process = new Process([
                'bash',
                $path,
                $config['name']
            ]);
            $process->run();
        }

        return redirect()->route('configs.index');
    }

    public function edit(Config $config)
    {
        $users = User::get();

        return view('configs.edit', compact('config', 'users'));
    }

    public function update(Request $request, Config $config)
    {
        $config->update($request->post());
        return redirect()->route('configs.index');
    }

    public function destroy(Config $config)
    {
        $config->delete();
        return redirect()->route('configs.index');
    }

    public function qrCode(Request $request, UserToken $userToken, Config $config)
    {
        if (! $userToken->validateToken($request->password)) {
            abort(404);
        }

        try {
            $configBody = file_get_contents($config->path);

            return QrCode::size(600)->generate($configBody);
        } catch (\Exception $exception) {
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
        } catch (\Exception $exception) {
            abort(500);
        }
    }
}
