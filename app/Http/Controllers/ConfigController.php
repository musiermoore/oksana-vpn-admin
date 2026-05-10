<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConfigResource;
use App\Http\Resources\ServerResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\Config\StoreBulkConfigRequest;
use App\Http\Requests\Config\StoreConfigRequest;
use App\Http\Requests\Config\UpdateConfigRequest;
use App\Models\Config;
use App\Models\Server;
use App\Models\User;
use App\Models\UserToken;
use App\Services\Crud\ConfigCrudService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ConfigController extends Controller
{
    public function __construct(
        private readonly ConfigCrudService $configService,
    ) {}

    public function index(Request $request)
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
                'configs' => ConfigResource::collection($user->configs)->toArray($request),
            ])->values(),
            'tabs' => [
                ['label' => 'WireGuard', 'href' => route('configs.index'), 'active' => true],
                ['label' => 'VLESS', 'href' => route('vless-configs.index'), 'active' => false],
            ],
        ]);
    }

    public function create(Request $request)
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
            'users' => UserResource::collection($users)->toArray($request),
            'servers' => ServerResource::collection($servers)->toArray($request),
            'file_names' => $fileNames->values(),
        ]);
    }

    public function store(StoreConfigRequest $request)
    {
        $failedConfigs = $this->configService->createMany($request->toDto());

        if ($failedConfigs !== []) {
            return redirect()->back()
                ->with('error', 'Некоторые из конфигов не были созданы: '.implode(', ', $failedConfigs));
        }

        return redirect()->route('configs.index')
            ->with('success', 'Конфиги успешно созданы');
    }

    public function createBulk(Request $request)
    {
        $servers = Server::get();

        return $this->inertia('Configs/BulkCreate', [
            'submit_url' => route('configs.store-bulk'),
            'servers' => ServerResource::collection($servers)->toArray($request),
        ]);
    }

    public function storeBulk(StoreBulkConfigRequest $request)
    {
        $failedConfigs = $this->configService->createBulk($request->toDto());

        if ($failedConfigs !== []) {
            return redirect()->back()
                ->with('error', 'Некоторые из конфигов не были созданы: '.implode(', ', $failedConfigs));
        }

        return redirect()->route('configs.index')
            ->with('success', 'Конфиги успешно созданы');
    }

    public function edit(Request $request, Config $config)
    {
        $users = User::get();
        $servers = Server::get();

        return $this->inertia('Configs/Edit', [
            'submit_url' => route('configs.update', $config),
            'config' => new ConfigResource($config),
            'users' => UserResource::collection($users)->toArray($request),
            'servers' => ServerResource::collection($servers)->toArray($request),
        ]);
    }

    public function update(UpdateConfigRequest $request, Config $config)
    {
        $this->configService->update($config, $request->toDto());

        return redirect()->route('configs.index')
            ->with('success', 'Конфиг успешно обновлён');
    }

    public function destroy(Config $config)
    {
        try {
            $this->configService->delete($config);
        } catch (RuntimeException) {
            return redirect()->route('configs.index')
                ->with('error', 'Ошибка при удалении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно удалён');
    }

    public function enable(Config $config)
    {
        try {
            $this->configService->enable($config);
        } catch (RuntimeException) {
            return redirect()->route('configs.index')
                ->with('error', 'Ошибка при включении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно включён');
    }

    public function disable(Config $config)
    {
        try {
            $this->configService->disable($config);
        } catch (RuntimeException) {
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
            report($exception);
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
                preg_replace('/[^a-zA-Z0-9]/', '', $config->name).'.conf'
            );
        } catch (Exception $exception) {
            report($exception);
            abort(500);
        }
    }
}
