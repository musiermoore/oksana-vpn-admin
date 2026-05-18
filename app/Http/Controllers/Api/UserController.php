<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\DTOs\User\ApiUserRegistrationData;
use App\Http\Requests\Api\RegisterApiUserRequest;
use App\Http\Resources\Api\ApiBalanceResource;
use App\Http\Resources\Api\ApiConfigResource;
use App\Http\Resources\Api\ApiRegisteredUserResource;
use App\Http\Resources\Api\ApiRegistrationStatusResource;
use App\Http\Resources\Api\ApiVlessConfigResource;
use App\Support\BotApiMessages;
use App\Services\Api\ApiUserService;
use Exception;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
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

    public function balance()
    {
        return response()->json((new ApiBalanceResource(request()->user()))->resolve());
    }

    public function getUserConfigs(Request $request, string $type)
    {
        $user = $request->user();
        $configs = $this->userService->getUserConfigs($user, $type);

        $resource = $this->userService->isVlessType($type)
            ? ApiVlessConfigResource::collection($configs)
            : ApiConfigResource::collection($configs);

        return response()->json([
            'configs' => $resource->resolve(),
        ]);
    }

    public function downloadConfig(Request $request, string $type, string $configId)
    {
        $config = $this->userService->findUserConfig($request->user(), $type, $configId);

        if (empty($config)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            return $this->userService->isVlessType($type)
                ? response($config->getLink())
                : response()->download($config->path, $config->name . '.conf');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function downloadQrCode(Request $request, string $type, string $configId)
    {
        $config = $this->userService->findUserConfig($request->user(), $type, $configId);

        if (empty($config)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            $configBody = $config->getQrCodeContent();

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
        return response($this->userService->getVlessLink(request()->user()));
    }

    public function getVlessQrCode()
    {
        try {
            $png = QrCode::format('png')
                ->margin(5)
                ->size(512)
                ->generate($this->userService->getVlessLink(request()->user()));

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
}
