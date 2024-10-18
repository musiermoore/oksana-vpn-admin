<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Limit;
use Illuminate\Http\Request;

class LimitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $configs = Config::query()
            ->withWhereHas('limits')
            ->with(['user', 'server'])
            ->get();

        return view('limits.index', compact('configs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $configs = Config::query()->with('user')->get();
        $speedLimits = Limit::getSpeedLimits();

        return view('limits.create', compact('configs', 'speedLimits'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $config = Config::find($request->config_id);

        if (empty($config)) {
            return redirect()->back()
                ->with('error', 'Конфиг не найден.');
        }

        $result = $config->setSpeedLimit($request->amount);

        if (!$result) {
            return redirect()->back()
                ->with('error', 'Команда выполнилась с ошибкой.');
        }

        $config->limits()->create($request->post());

        return redirect()->route('limits.index')
            ->with('success', 'Ограничение успешно создано.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Limit $limit)
    {
        $config = $limit->config;

        if (empty($config)) {
            return redirect()->back()
                ->with('error', 'Конфиг не найден.');
        }

        $result = $config->removeSpeedLimit($limit->amount);

        if (!$result) {
            return redirect()->back()
                ->with('error', 'Команда выполнилась с ошибкой.');
        }

        $limit->delete();

        return redirect()->route('limits.index')
            ->with('success', 'Ограничение успешно удалёно');
    }
}
