<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ConfigController extends Controller
{
    public function index()
    {
        $configs = Config::all();
        return view('configs.index', compact('configs'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();

        return view('configs.create', compact('users'));
    }

    public function store(Request $request)
    {
        $user = User::find($request->user_id);

        $user->configs()->createMany($request->post('configs', []));

        return redirect()->route('configs.index');
    }

    public function edit(Config $config)
    {
        $users = User::orderBy('name')->get();

        return view('configs.edit', compact('config', 'users'));
    }

    public function update(Request $request, Config $config)
    {
        $user = User::find($request->user_id);

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
