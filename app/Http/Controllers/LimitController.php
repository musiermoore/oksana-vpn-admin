<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsInertiaData;
use App\Http\Requests\Limit\StoreLimitRequest;
use App\Models\Config;
use App\Models\Limit;
use App\Services\Crud\LimitCrudService;
use RuntimeException;

class LimitController extends Controller
{
    use BuildsInertiaData;

    public function __construct(
        private readonly LimitCrudService $limitService,
    ) {}

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
    public function store(StoreLimitRequest $request)
    {
        try {
            $this->limitService->create($request->toDto());
        } catch (RuntimeException $exception) {
            return redirect()->back()
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('limits.index')
            ->with('success', 'Ограничение успешно создано.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Limit $limit)
    {
        try {
            $this->limitService->delete($limit);
        } catch (RuntimeException $exception) {
            return redirect()->back()
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('limits.index')
            ->with('success', 'Ограничение успешно удалёно');
    }
}
