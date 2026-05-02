<?php

namespace App\Http\Controllers;

use App\Http\Requests\VlessConfig\StoreVlessConfigRequest;
use App\Http\Requests\VlessConfig\UpdateVlessConfigRequest;
use App\Models\User;
use App\Models\UserToken;
use App\Models\VlessConfig;
use App\Services\Crud\VlessConfigCrudService;
use App\Services\VlessSubscriptionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VlessConfigController extends Controller
{
    public function __construct(
        private readonly VlessConfigCrudService $vlessConfigService,
    ) {}

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

        return $this->inertia('Configs/VlessIndex', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'is_active' => $user->is_active,
                'edit_url' => route('users.edit', $user),
                'configs' => $user->vlessConfigs->map(fn (VlessConfig $config) => $this->vlessConfigData($config))->values(),
            ])->values(),
            'tabs' => [
                ['label' => 'WireGuard', 'href' => route('configs.index'), 'active' => false],
                ['label' => 'VLESS', 'href' => route('vless-configs.index'), 'active' => true],
            ],
        ]);
    }

    public function create()
    {
        $users = User::query()
            ->where('users.is_active', true)
            ->get();

        $existingConfigs = VlessConfig::query()
            ->selectRaw('vless_configs.id, CONCAT(servers.code, ": ", vless_configs.name) AS formatted_name')
            ->join('servers', 'servers.id', '=', 'vless_configs.server_id')
            ->whereNull('vless_configs.user_id')
            ->get()
            ->pluck('formatted_name', 'id');

        return $this->inertia('Configs/VlessForm', [
            'mode' => 'create',
            'submit_url' => route('vless-configs.store'),
            'config' => null,
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
            'existing_configs' => $existingConfigs->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
        ]);
    }

    public function store(StoreVlessConfigRequest $request)
    {
        try {
            $this->vlessConfigService->assign($request->toDto());
        } catch (RuntimeException $exception) {
            return redirect()->back()
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('vless-configs.index')
            ->with('success', 'Конфиги успешно созданы');
    }

    public function edit(VlessConfig $vlessConfig)
    {
        $config = $vlessConfig;
        $users = User::query()
            ->where('users.is_active', true)
            ->get();

        return $this->inertia('Configs/VlessForm', [
            'mode' => 'edit',
            'submit_url' => route('vless-configs.update', $config),
            'config' => $this->vlessConfigData($config),
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
            'existing_configs' => [],
        ]);
    }

    public function update(UpdateVlessConfigRequest $request, VlessConfig $vlessConfig)
    {
        $this->vlessConfigService->update($vlessConfig, $request->toDto());

        return redirect()->route('vless-configs.index')
            ->with('success', 'Конфиг успешно обновлён');
    }

    public function destroy(VlessConfig $vlessConfig)
    {
        $this->vlessConfigService->unassign($vlessConfig);

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
            report($exception);
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
                preg_replace('/[^a-zA-Z0-9]/', '', $config->name).'.conf'
            );
        } catch (Exception $exception) {
            report($exception);
            abort(500);
        }
    }

    public function connect(Request $request)
    {
        try {
            $telegramId = Crypt::decrypt($request->tg);
            $userId = Crypt::decrypt($request->i);
        } catch (Exception $exception) {
            return null;
        }

        if (empty($telegramId) || empty($userId)) {
            return null;
        }

        $user = User::query()->whereTelegramId($telegramId)->find($userId);

        $service = new VlessSubscriptionService($user);
        $subscriptions = $service->getAllSubscriptions();

        return response($subscriptions);
    }
}
