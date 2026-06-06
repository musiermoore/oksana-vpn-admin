<?php

namespace App\Http\Controllers;

use App\Http\Requests\XrayConfig\StoreXrayConfigRequest;
use App\Http\Requests\XrayConfig\UpdateXrayConfigRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\XrayConfigResource;
use App\Models\Server;
use App\Models\ShadowsocksConfig;
use App\Models\User;
use App\Models\VlessConfig;
use App\Services\Crud\ShadowsocksConfigCrudService;
use App\Services\Crud\VlessConfigCrudService;
use App\Services\XuiConfigServiceFactory;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RuntimeException;

class XrayConfigController extends Controller
{
    public function __construct(
        private readonly VlessConfigCrudService $vlessConfigService,
        private readonly ShadowsocksConfigCrudService $shadowsocksConfigService,
    ) {}

    public function index(Request $request)
    {
        $users = User::query()
            ->with([
                'vlessConfigs.user',
                'vlessConfigs.server',
                'shadowsocksConfigs.user',
                'shadowsocksConfigs.server',
            ])
            ->where(function ($query) {
                $query->whereHas('vlessConfigs')
                    ->orWhereHas('shadowsocksConfigs');
            })
            ->orderByDesc('deleted_at')
            ->orderBy('created_at')
            ->get();

        return $this->inertia('Configs/XrayIndex', [
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'is_active' => $user->is_active,
                'edit_url' => route('users.edit', $user),
                'configs' => $this->getUserConfigsPayload($user, $request),
            ])->values(),
            'tabs' => [
                ['label' => 'WireGuard', 'href' => route('configs.index'), 'active' => false],
                ['label' => 'Xray Configs', 'href' => route('xray-configs.index'), 'active' => true],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $users = User::query()
            ->where('users.is_active', true)
            ->get();

        return $this->inertia('Configs/XrayForm', [
            'mode' => 'create',
            'submit_url' => route('xray-configs.store'),
            'config' => null,
            'selected_user_id' => $request->integer('user_id') ?: null,
            'users' => UserResource::collection($users)->toArray($request),
            'available_inbounds' => $this->getAvailableInbounds(),
        ]);
    }

    public function store(StoreXrayConfigRequest $request)
    {
        try {
            $data = $request->toDto();

            if ($data->protocol === 'vless') {
                $this->vlessConfigService->assignFromXrayData($data->userId, $data->serverId, $data->inboundId);
            } else {
                $this->shadowsocksConfigService->create($data->userId, $data->serverId, $data->inboundId);
            }
        } catch (RuntimeException $exception) {
            return redirect()->back()
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('xray-configs.index')
            ->with('success', 'Конфиги успешно созданы');
    }

    public function edit(Request $request, string $protocol, string $config)
    {
        $model = $this->resolveConfig($protocol, $config);
        $users = User::query()
            ->where('users.is_active', true)
            ->get();

        return $this->inertia('Configs/XrayForm', [
            'mode' => 'edit',
            'submit_url' => route('xray-configs.update', compact('protocol', 'config')),
            'config' => (new XrayConfigResource($model, $protocol))->toArray($request),
            'users' => UserResource::collection($users)->toArray($request),
            'available_inbounds' => [],
        ]);
    }

    public function update(UpdateXrayConfigRequest $request, string $protocol, string $config)
    {
        $model = $this->resolveConfig($protocol, $config);
        $userId = $request->toDto()->userId;

        if ($protocol === 'vless') {
            $this->vlessConfigService->update($model, new \App\DTOs\VlessConfig\VlessConfigUpdateData($userId));
        } else {
            $this->shadowsocksConfigService->update($model, $userId);
        }

        return redirect()->route('xray-configs.index')
            ->with('success', 'Конфиг успешно обновлён');
    }

    public function destroy(string $protocol, string $config)
    {
        $model = $this->resolveConfig($protocol, $config);

        if ($protocol === 'vless') {
            $this->vlessConfigService->unassign($model);
        } else {
            $this->shadowsocksConfigService->unassign($model);
        }

        return redirect()->back()
            ->with('success', 'Конфиг отвязан от пользователя');
    }

    public function enable(string $protocol, string $config)
    {
        if ($protocol !== 'vless') {
            return redirect()->route('xray-configs.index')
                ->with('error', 'Включение доступно только для VLESS-конфигов');
        }

        try {
            $this->vlessConfigService->enable($this->resolveConfig($protocol, $config));
        } catch (RuntimeException) {
            return redirect()->route('xray-configs.index')
                ->with('error', 'Ошибка при включении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно включён');
    }

    public function disable(string $protocol, string $config)
    {
        if ($protocol !== 'vless') {
            return redirect()->route('xray-configs.index')
                ->with('error', 'Отключение доступно только для VLESS-конфигов');
        }

        try {
            $this->vlessConfigService->disable($this->resolveConfig($protocol, $config));
        } catch (RuntimeException) {
            return redirect()->route('xray-configs.index')
                ->with('error', 'Ошибка при отключении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно отключён');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getUserConfigsPayload(User $user, Request $request): array
    {
        return collect([
            ...$user->vlessConfigs->map(fn (VlessConfig $config) => new XrayConfigResource($config, 'vless'))->all(),
            ...$user->shadowsocksConfigs->map(fn (ShadowsocksConfig $config) => new XrayConfigResource($config, 'shadowsocks'))->all(),
        ])
            ->map(fn (XrayConfigResource $resource) => $resource->toArray($request))
            ->sortBy([
                fn (array $config) => $config['protocol'],
                fn (array $config) => $config['server']['code'] ?? '',
                fn (array $config) => $config['name'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAvailableInbounds(): array
    {
        return Server::query()
            ->where('is_vless', true)
            ->where('is_ready', true)
            ->orderBy('name')
            ->get()
            ->flatMap(function (Server $server) {
                try {
                    $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
                    $vless = collect($service->getAllVlessInbounds())
                        ->map(fn (array $inbound) => $this->mapInboundForForm($server, $inbound, 'vless'));
                    $shadowsocks = collect($service->getAllShadowsocksInbounds())
                        ->map(fn (array $inbound) => $this->mapInboundForForm($server, $inbound, 'shadowsocks'));

                    return $vless->concat($shadowsocks)->values()->all();
                } catch (Exception $exception) {
                    report($exception);

                    return [];
                }
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @return array<string, mixed>
     */
    private function mapInboundForForm(Server $server, array $inbound, string $protocol): array
    {
        $parts = [
            strtoupper($protocol),
            strtoupper((string) ($inbound['type'] ?? 'unknown')),
            strtoupper((string) ($inbound['security'] ?? 'none')),
            'port '.$inbound['port'],
        ];

        if (! empty($inbound['method'])) {
            $parts[] = 'method '.$inbound['method'];
        }

        if (! empty($inbound['host'])) {
            $parts[] = 'host '.$inbound['host'];
        }

        if (! empty($inbound['path'])) {
            $parts[] = 'path '.$inbound['path'];
        }

        if (! empty($inbound['service_name'])) {
            $parts[] = 'service '.$inbound['service_name'];
        }

        return [
            'protocol' => $protocol,
            'server_id' => $server->id,
            'server_name' => $server->name,
            'server_code' => $server->code,
            'inbound_id' => $inbound['id'],
            'type' => $inbound['type'],
            'security' => $inbound['security'],
            'label' => $server->code.': '.implode(', ', array_filter($parts)),
        ];
    }

    private function resolveConfig(string $protocol, string $config): Model
    {
        return match ($protocol) {
            'vless' => VlessConfig::query()->findOrFail($config),
            'shadowsocks' => ShadowsocksConfig::query()->findOrFail($config),
            default => abort(404),
        };
    }
}
