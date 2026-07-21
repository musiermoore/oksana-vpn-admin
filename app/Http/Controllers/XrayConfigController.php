<?php

namespace App\Http\Controllers;

use App\DTOs\VlessConfig\VlessConfigUpdateData;
use App\Http\Requests\XrayConfig\StoreXrayConfigRequest;
use App\Http\Requests\XrayConfig\UpdateXrayConfigRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\XrayConfigResource;
use App\Models\Server;
use App\Models\User;
use App\Models\VlessConfig;
use App\Services\Crud\VlessConfigCrudService;
use App\Services\XuiConfigServiceFactory;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;

class XrayConfigController extends Controller
{
    public function __construct(
        private readonly VlessConfigCrudService $vlessConfigService,
    ) {}

    public function index(Request $request)
    {
        $users = User::query()
            ->with([
                'vlessConfigs.user',
                'vlessConfigs.server',
            ])
            ->whereHas('vlessConfigs')
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
            'available_inbounds' => $this->getAvailableInbounds($request->user()),
        ]);
    }

    public function store(StoreXrayConfigRequest $request)
    {
        try {
            $data = $request->toDto();
            $this->vlessConfigService->assignFromXrayData($data->userId, $data->serverId, $data->inboundId);
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

        $this->vlessConfigService->update($model, new VlessConfigUpdateData($userId));

        return redirect()->route('xray-configs.index')
            ->with('success', 'Конфиг успешно обновлён');
    }

    public function destroy(string $protocol, string $config)
    {
        $model = $this->resolveConfig($protocol, $config);
        $this->vlessConfigService->unassign($model);

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
    private function getAvailableInbounds(?User $viewer = null): array
    {
        return Server::query()
            ->vless()
            ->where('is_active', true)
            ->where('is_ready', true)
            ->orderBy('name')
            ->get()
            ->flatMap(function (Server $server) {
                try {
                    $visibilityByInboundId = $server->xrayInbounds()
                        ->get(['external_id', 'is_active', 'is_public'])
                        ->keyBy(fn ($inbound) => (int) $inbound->external_id);

                    return collect(XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server)->getAllVlessInbounds())
                        ->filter(function (array $inbound) use ($viewer, $visibilityByInboundId): bool {
                            $meta = $visibilityByInboundId->get((int) ($inbound['id'] ?? 0));

                            if ($meta !== null && ! $meta->is_active) {
                                return false;
                            }

                            return $meta === null || $meta->is_public || (bool) ($viewer?->is_admin);
                        })
                        ->map(function (array $inbound) use ($server, $visibilityByInboundId): array {
                            $meta = $visibilityByInboundId->get((int) ($inbound['id'] ?? 0));

                            return $this->mapInboundForForm(
                                $server,
                                $inbound,
                                'vless',
                                $meta === null ? true : (bool) $meta->is_public,
                            );
                        })
                        ->values()
                        ->all();
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
    private function mapInboundForForm(Server $server, array $inbound, string $protocol, bool $isPublic = true): array
    {
        $displayProtocol = mb_strtoupper((string) ($inbound['protocol'] ?? $protocol));

        $parts = [
            $displayProtocol,
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
            'is_public' => $isPublic,
            'type' => $inbound['type'],
            'security' => $inbound['security'],
            'label' => $server->code.': '.implode(', ', array_filter($parts)),
        ];
    }

    private function resolveConfig(string $protocol, string $config): VlessConfig
    {
        return match ($protocol) {
            'vless' => VlessConfig::query()->findOrFail($config),
            default => abort(404),
        };
    }
}
