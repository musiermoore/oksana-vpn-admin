<?php

namespace App\Http\Controllers\Api;

use App\DTOs\User\ApiUserRegistrationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterApiUserRequest;
use App\Http\Resources\Api\ApiBalanceResource;
use App\Http\Resources\Api\ApiConfigResource;
use App\Http\Resources\Api\ApiRegisteredUserResource;
use App\Http\Resources\Api\ApiRegistrationStatusResource;
use App\Http\Resources\Api\ApiShadowsocksConfigResource;
use App\Http\Resources\Api\ApiSubscriptionPackageResource;
use App\Http\Resources\Api\ApiVlessConfigResource;
use App\Http\Resources\Api\ApiVlessDeepLinksResource;
use App\Models\Config;
use App\Models\User;
use App\Services\Api\ApiUserService;
use App\Services\WireGuardAgentConfigService;
use App\Support\BotApiMessages;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        private readonly ApiUserService $userService,
    ) {}

    public function registrationStatus(string $telegramId)
    {
        try {
            $user = $this->userService->findActiveUserByTelegramId($telegramId);

            return response()->json(
                (new ApiRegistrationStatusResource(
                    $user,
                    $user ? $this->userService->hasMoneyForNextSubscriptionMonth($user) : false,
                ))->resolve()
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function balance(): JsonResponse
    {
        $user = $this->resolveApiUser();

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json((new ApiBalanceResource($user))->resolve());
    }

    public function subscriptionPackages(): JsonResponse
    {
        $user = $this->resolveApiUser();

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json([
            'data' => ApiSubscriptionPackageResource::collection(
                $this->userService->getSubscriptionPackages($user)
            )->resolve(),
        ]);
    }

    public function getUserConfigs(Request $request, string $telegramId, string $type): JsonResponse
    {
        $user = $this->resolveApiUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $configs = $this->userService->getUserConfigs($user, $type);

        $resource = match (true) {
            $this->userService->isVlessType($type) => ApiVlessConfigResource::collection($configs),
            $this->userService->isShadowsocksType($type) => ApiShadowsocksConfigResource::collection($configs),
            default => ApiConfigResource::collection($configs),
        };

        return response()->json([
            'configs' => $resource->resolve(),
        ]);
    }

    public function downloadConfig(Request $request, string $telegramId, string $type, string $configId): Response
    {
        $user = $this->resolveApiUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        /** @var Config $config */
        $config = $this->userService->findUserConfig($user, $type, $configId);

        if (empty($config)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            return $this->userService->isLinkConfigType($type)
                ? response($config->getLink())
                : $this->downloadWireGuardConfig($config);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function downloadQrCode(Request $request, string $telegramId, string $type, string $configId): Response
    {
        $user = $this->resolveApiUser($request);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $config = $this->userService->findUserConfig($user, $type, $configId);

        if (empty($config)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            $configBody = $this->resolveQrCodeContent($config);

            $png = QrCode::format('png')->margin(5)->size(512)->generate($configBody);

            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="qrcode.png"');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function register(RegisterApiUserRequest $request)
    {
        return $this->registrationResponse($request->toDto());
    }

    public function saveTelegramId(RegisterApiUserRequest $request, string $telegramId)
    {
        return $this->registrationResponse($request->toDto($telegramId));
    }

    public function getVlessLink()
    {
        $user = $this->resolveApiUser();

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json(
            (new ApiVlessDeepLinksResource($this->userService->getVlessLinks($user)))->resolve()
        );
    }

    public function getVlessQrCode()
    {
        $user = $this->resolveApiUser();

        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $png = QrCode::format('png')
                ->margin(5)
                ->size(512)
                ->generate($this->userService->getVlessLink($user));

            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="vless-qrcode.png"');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    private function registrationResponse(ApiUserRegistrationData $data)
    {
        $result = $this->userService->register($data);

        return response()->json([
            'message' => $result->created
                ? 'Регистрация выполнена. Теперь можно пользоваться ботом.'
                : 'Telegram успешно привязан.',
            'user' => (new ApiRegisteredUserResource($result->user))->resolve(),
        ], $result->created ? 201 : 200);
    }

    private function resolveApiUser(?Request $request = null): User|JsonResponse
    {
        $request ??= request();

        $user = $request->attributes->get('apiUser');

        if ($user instanceof User) {
            return $user;
        }

        $resolvedUser = $request->user();

        if ($resolvedUser instanceof User) {
            return $resolvedUser;
        }

        return response()->json([
            'message' => BotApiMessages::userNotFound(),
        ], 404);
    }

    private function downloadWireGuardConfig(Config $config): Response
    {
        if (! $config->server->isModernWireGuardType()) {
            return response()->download($config->path, $config->name.'.conf');
        }

        $directory = storage_path('app/tmp/wireguard-downloads');

        File::ensureDirectoryExists($directory);

        $temporaryPath = $directory.'/'.Str::uuid().'.conf';
        $content = WireGuardAgentConfigService::instance($config)->getClientConfig();

        File::put($temporaryPath, $content.PHP_EOL);

        return response()
            ->download($temporaryPath, $config->name.'.conf')
            ->deleteFileAfterSend(true);
    }

    private function resolveQrCodeContent(mixed $config): string
    {
        if ($config instanceof Config && $config->server->isModernWireGuardType()) {
            return WireGuardAgentConfigService::instance($config)->getClientConfig();
        }

        return $config->getQrCodeContent();
    }
}
