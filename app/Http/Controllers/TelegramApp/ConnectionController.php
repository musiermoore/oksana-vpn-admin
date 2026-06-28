<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
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

class ConnectionController extends Controller
{
    public function __construct(
        private readonly ApiUserService $users,
    ) {}

    public function wireGuardConfigs(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $configs = $this->users->getUserConfigs($user, 'wireguard');

        return response()->json([
            'configs' => $configs
                ->map(fn (Config $config) => [
                    'id' => $config->id,
                    'name' => $config->name,
                    'download_url' => route('telegram-app.wireguard.configs.download', [
                        'configId' => $config->id,
                    ], absolute: false),
                    'qr_code_url' => route('telegram-app.wireguard.configs.qr-code', [
                        'configId' => $config->id,
                    ], absolute: false),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function wireGuardDownload(Request $request, int $configId): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $config = $this->users->findUserConfig($user, 'wireguard', $configId);

        if (! $config instanceof Config) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            if (! $config->server->isModernWireGuardType()) {
                return response()->download($config->path, $config->download_filename);
            }

            $directory = storage_path('app/tmp/wireguard-downloads');

            File::ensureDirectoryExists($directory);

            $temporaryPath = $directory.'/'.Str::uuid().'.conf';
            $content = WireGuardAgentConfigService::instance($config)->getClientConfig();

            File::put($temporaryPath, $content.PHP_EOL);

            return response()
                ->download($temporaryPath, $config->download_filename)
                ->deleteFileAfterSend(true);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function wireGuardQrCode(Request $request, int $configId): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $config = $this->users->findUserConfig($user, 'wireguard', $configId);

        if (! $config instanceof Config) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            $content = $config->server->isModernWireGuardType()
                ? WireGuardAgentConfigService::instance($config)->getClientConfig()
                : $config->getQrCodeContent();

            $png = QrCode::format('png')
                ->margin(5)
                ->size(512)
                ->generate($content);

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

    public function vlessLinks(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        try {
            return response()->json(
                (new ApiVlessDeepLinksResource($this->users->getVlessLinks($user)))->resolve()
            );
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function vlessQrCode(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        try {
            $png = QrCode::format('png')
                ->margin(5)
                ->size(512)
                ->generate($this->users->getVlessLink($user));

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

    private function ensureActiveAccess(User $user): ?JsonResponse
    {
        if ($user->hasActiveAccess()) {
            return null;
        }

        return response()->json([
            'type' => 'debt',
            'message' => BotApiMessages::accessRequiresPayment(),
        ], 403);
    }
}
