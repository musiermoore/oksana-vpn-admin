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
            ->orderBy('user_id')
            ->orderBy('server_id')
            ->get();

        return $this->inertia('Limits/Index', [
            'configs' => $configs->map(function (Config $config) {
                return [
                    ...$this->configData($config),
                    'limits' => $config->limits->map(fn (Limit $limit) => $this->limitData($limit))->values(),
                ];
            })->values(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $configs = Config::query()->with('user')->get();
        $speedLimits = Limit::getSpeedLimits();

        return $this->inertia('Limits/Create', [
            'submit_url' => route('limits.store'),
            'configs' => $configs->map(fn (Config $config) => $this->configData($config))->values(),
            'speed_limits' => $speedLimits,
        ]);
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
