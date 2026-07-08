<?php

namespace App\Http\Controllers;

use App\Http\Requests\VlessConfig\StoreVlessConfigRequest;
use App\Http\Requests\VlessConfig\UpdateVlessConfigRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\VlessConfigResource;
use App\Models\Server;
use App\Models\User;
use App\Models\UserToken;
use App\Models\VlessConfig;
use App\Services\Crud\VlessConfigCrudService;
use App\Services\ExternalSubscriptions\VlessExternalSubscriptionAccessService;
use App\Services\SubscriptionMetadataService;
use App\Services\Subscriptions\UserSubscriptionService;
use App\Services\VlessDeepLinkService;
use App\Services\VlessSubscriptionService;
use App\Services\XuiConfigServiceFactory;
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

    public function index(Request $request)
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
                'configs' => VlessConfigResource::collection($user->vlessConfigs)->toArray($request),
            ])->values(),
            'tabs' => [
                ['label' => 'WireGuard', 'href' => route('configs.index'), 'active' => false],
                ['label' => 'VLESS', 'href' => route('vless-configs.index'), 'active' => true],
            ],
        ]);
    }

    public function create(Request $request)
    {
        $users = User::query()
            ->where('users.is_active', true)
            ->get();

        return $this->inertia('Configs/VlessForm', [
            'mode' => 'create',
            'submit_url' => route('vless-configs.store'),
            'config' => null,
            'users' => UserResource::collection($users)->toArray($request),
            'available_inbounds' => $this->getAvailableInbounds(),
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

    public function edit(Request $request, VlessConfig $vlessConfig)
    {
        $config = $vlessConfig;
        $users = User::query()
            ->where('users.is_active', true)
            ->get();

        return $this->inertia('Configs/VlessForm', [
            'mode' => 'edit',
            'submit_url' => route('vless-configs.update', $config),
            'config' => (new VlessConfigResource($config))->toArray($request),
            'users' => UserResource::collection($users)->toArray($request),
            'available_inbounds' => [],
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

    public function enable(VlessConfig $vlessConfig)
    {
        try {
            $this->vlessConfigService->enable($vlessConfig);
        } catch (RuntimeException) {
            return redirect()->route('vless-configs.index')
                ->with('error', 'Ошибка при включении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно включён');
    }

    public function disable(VlessConfig $vlessConfig)
    {
        try {
            $this->vlessConfigService->disable($vlessConfig);
        } catch (RuntimeException) {
            return redirect()->route('vless-configs.index')
                ->with('error', 'Ошибка при отключении конфига');
        }

        return redirect()->back()
            ->with('success', 'Конфиг успешно отключён');
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

    public function connect(
        Request $request,
        SubscriptionMetadataService $metadataService,
        UserSubscriptionService $subscriptionService
    )
    {
        $user = $this->resolveUserFromConnectionRequest($request);

        if (! $user) {
            return null;
        }

        $subscription = $subscriptionService->build($user, $request->query('format'));

        $response = response($subscription->content);

        foreach ($metadataService->buildHeaders(
            $user,
            $subscription->fileExtension,
            $subscription->contentType,
        ) as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    public function connectWhiteList(
        Request $request,
        SubscriptionMetadataService $metadataService,
        UserSubscriptionService $subscriptionService,
        VlessExternalSubscriptionAccessService $externalSubscriptions
    ) {
        $user = $this->resolveUserFromConnectionRequest($request);

        if (! $user) {
            return null;
        }

        $subscription = $subscriptionService->buildFromNodes(
            $externalSubscriptions->getNamedNodesForUser($user),
            $request->query('format')
        );

        $response = response($subscription->content);

        foreach ($metadataService->buildHeaders(
            $user,
            $subscription->fileExtension,
            $subscription->contentType,
        ) as $name => $value) {
            $response->header($name, $value);
        }

        return $response;
    }

    public function connectRaw(Request $request, UserSubscriptionService $subscriptionService)
    {
        $user = $this->resolveUserFromConnectionRequest($request);

        if (! $user) {
            return null;
        }

        return response()->json($subscriptionService->buildDebug($user));
    }

    public function connectWhiteListRaw(
        Request $request,
        VlessExternalSubscriptionAccessService $externalSubscriptions
    ) {
        $user = $this->resolveUserFromConnectionRequest($request);

        if (! $user) {
            return null;
        }

        return response()->json($externalSubscriptions->buildDebug($user));
    }

    public function deepLink(Request $request, string $client, VlessDeepLinkService $deepLinkService)
    {
        $user = $this->resolveUserFromConnectionRequest($request);

        if (! $user) {
            abort(404);
        }

        $redirectUrl = $deepLinkService->resolveRedirectUrl($client, $deepLinkService->getConnectUrl($user, $client));

        if ($redirectUrl === null) {
            abort(404);
        }

        return redirect()->away($redirectUrl);
    }

    public function deepLinkWhiteList(
        Request $request,
        string $client,
        VlessDeepLinkService $deepLinkService
    ) {
        $user = $this->resolveUserFromConnectionRequest($request);

        if (! $user) {
            abort(404);
        }

        $redirectUrl = $deepLinkService->resolveRedirectUrl(
            $client,
            $deepLinkService->getConnectUrlForRoute($user, 'vless.connect-wl', $client)
        );

        if ($redirectUrl === null) {
            abort(404);
        }

        return redirect()->away($redirectUrl);
    }

    private function resolveUserFromConnectionRequest(Request $request): ?User
    {
        $credentials = $this->resolveConnectionCredentials($request);

        if ($credentials === null) {
            return null;
        }

        return User::query()
            ->whereTelegramId($credentials['tg'])
            ->find($credentials['i']);
    }

    /**
     * @return array{tg: string, i: int}|null
     */
    private function resolveConnectionCredentials(Request $request): ?array
    {
        try {
            if ($request->filled('token')) {
                $payload = Crypt::decrypt($request->string('token')->toString());

                if (! is_array($payload)) {
                    return null;
                }

                $telegramId = (string) ($payload['tg'] ?? '');
                $userId = (int) ($payload['i'] ?? 0);
            } else {
                $telegramId = (string) Crypt::decrypt($request->tg);
                $userId = (int) Crypt::decrypt($request->i);
            }
        } catch (Exception) {
            return null;
        }

        if ($telegramId === '' || $userId <= 0) {
            return null;
        }

        return [
            'tg' => $telegramId,
            'i' => $userId,
        ];
    }

    private function getAvailableInbounds(): array
    {
        return Server::query()
            ->vless()
            ->where('is_active', true)
            ->where('is_ready', true)
            ->orderBy('name')
            ->get()
            ->flatMap(function (Server $server) {
                try {
                    $inbounds = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server)->getAllVlessInbounds();
                } catch (Exception $exception) {
                    report($exception);

                    return [];
                }

                return collect($inbounds)
                    ->map(function (array $inbound) use ($server) {
                        $parts = [
                            strtoupper((string) ($inbound['type'] ?? 'unknown')),
                            strtoupper((string) ($inbound['security'] ?? 'none')),
                            'port '.$inbound['port'],
                        ];

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
                            'server_id' => $server->id,
                            'server_name' => $server->name,
                            'server_code' => $server->code,
                            'inbound_id' => $inbound['id'],
                            'type' => $inbound['type'],
                            'security' => $inbound['security'],
                            'label' => $server->code.': '.implode(', ', array_filter($parts)),
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }
}
