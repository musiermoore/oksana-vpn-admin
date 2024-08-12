<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\User;
use Illuminate\Http\Request;

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

        $config->update($request->all());
        return redirect()->route('configs.index');
    }

    public function destroy(Config $config)
    {
        $config->delete();
        return redirect()->route('configs.index');
    }
}
