<?php

namespace App\Http\Controllers\TelegramApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ApiVlessDeepLinksResource;
use App\Models\Config;
use App\Models\User;
use App\Models\VlessConfig;
use App\Services\Api\ApiUserService;
use App\Services\WireGuardClientConfigBuilder;
use App\Services\WireGuardAgentConfigService;
use App\Support\WireGuardConfigPublicId;
use App\Support\BotApiMessages;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class ConnectionController extends Controller
{
    public function __construct(
        private readonly ApiUserService $users,
        private readonly WireGuardClientConfigBuilder $wireGuardClientConfigBuilder,
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
                ->map(function (Config|VlessConfig $config): array {
                    $configId = WireGuardConfigPublicId::encode($config);

                    return [
                        'id' => $configId,
                        'name' => $config->name,
                        'download_url' => route('telegram-app.wireguard.configs.download', [
                            'configId' => $configId,
                        ], absolute: false),
                        'qr_code_url' => route('telegram-app.wireguard.configs.qr-code', [
                            'configId' => $configId,
                        ], absolute: false),
                        'send_file_to_bot_url' => route('telegram-app.wireguard.configs.send-file', [
                            'configId' => $configId,
                        ], absolute: false),
                        'send_qr_to_bot_url' => route('telegram-app.wireguard.configs.send-qr', [
                            'configId' => $configId,
                        ], absolute: false),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function wireGuardDownload(Request $request, string $configId): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $config = $this->users->findUserConfig($user, 'wireguard', $configId);

        if (! ($config instanceof Config || $config instanceof VlessConfig)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            if ($config instanceof VlessConfig) {
                return $this->downloadXrayWireGuardConfig($config);
            }

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

    public function wireGuardQrCode(Request $request, string $configId): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $config = $this->users->findUserConfig($user, 'wireguard', $configId);

        if (! ($config instanceof Config || $config instanceof VlessConfig)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        try {
            $content = $config instanceof VlessConfig
                ? $this->wireGuardClientConfigBuilder->buildFromVlessConfig($config)
                : ($config->server->isModernWireGuardType()
                    ? WireGuardAgentConfigService::instance($config)->getClientConfig()
                    : $config->getQrCodeContent());

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

    public function wireGuardSendFile(Request $request, string $configId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $config = $this->users->findUserConfig($user, 'wireguard', $configId);

        if (! ($config instanceof Config || $config instanceof VlessConfig)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        $temporaryPath = null;

        try {
            [$path, $filename, $temporaryPath] = $config instanceof VlessConfig
                ? $this->resolveXrayWireGuardDocument($config)
                : $this->resolveWireGuardDocument($config);

            Telegram::sendDocument([
                'chat_id' => (string) $user->telegram_id,
                'document' => InputFile::create($path, $filename),
                'caption' => "Amnezia конфиг: {$config->name}",
            ]);

            return response()->json([
                'message' => 'Файл отправлен в бот.',
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось отправить файл в бота. Откройте диалог с ботом и попробуйте ещё раз.',
            ], 422);
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    public function wireGuardSendQr(Request $request, string $configId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $config = $this->users->findUserConfig($user, 'wireguard', $configId);

        if (! ($config instanceof Config || $config instanceof VlessConfig)) {
            return response()->json([
                'message' => BotApiMessages::configNotFound(),
            ], 404);
        }

        $temporaryPath = null;

        try {
            $png = $config instanceof VlessConfig
                ? $this->buildXrayWireGuardQrPng($config)
                : $this->buildWireGuardQrPng($config);
            $temporaryPath = $this->storeTemporaryTelegramFile($png, 'amnezia-qrcode.png');

            Telegram::sendPhoto([
                'chat_id' => (string) $user->telegram_id,
                'photo' => InputFile::create($temporaryPath, 'amnezia-qrcode.png'),
                'caption' => "Amnezia QR: {$config->name}",
            ]);

            return response()->json([
                'message' => 'QR-код отправлен в бот.',
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось отправить QR-код в бота. Откройте диалог с ботом и попробуйте ещё раз.',
            ], 422);
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function downloadXrayWireGuardConfig(VlessConfig $config): Response
    {
        $content = $this->wireGuardClientConfigBuilder->buildFromVlessConfig($config);
        $filename = $this->wireGuardClientConfigBuilder->buildDownloadFilename($config);

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveXrayWireGuardDocument(VlessConfig $config): array
    {
        $directory = storage_path('app/tmp/wireguard-downloads');

        File::ensureDirectoryExists($directory);

        $temporaryPath = $directory.'/'.Str::uuid().'.conf';

        File::put($temporaryPath, $this->wireGuardClientConfigBuilder->buildFromVlessConfig($config));

        return [
            $temporaryPath,
            $this->wireGuardClientConfigBuilder->buildDownloadFilename($config),
            $temporaryPath,
        ];
    }

    private function buildXrayWireGuardQrPng(VlessConfig $config): string
    {
        return QrCode::format('png')
            ->margin(5)
            ->size(512)
            ->generate($this->wireGuardClientConfigBuilder->buildFromVlessConfig($config));
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

    public function vlessSendQr(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $temporaryPath = null;

        try {
            $png = QrCode::format('png')
                ->margin(5)
                ->size(512)
                ->generate($this->users->getVlessLink($user));

            $temporaryPath = $this->storeTemporaryTelegramFile($png, 'vless-qrcode.png');

            Telegram::sendPhoto([
                'chat_id' => (string) $user->telegram_id,
                'photo' => InputFile::create($temporaryPath, 'vless-qrcode.png'),
                'caption' => 'VLESS QR-код',
            ]);

            return response()->json([
                'message' => 'QR-код отправлен в бот.',
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось отправить QR-код в бота. Откройте диалог с ботом и попробуйте ещё раз.',
            ], 422);
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    public function vlessWhiteListLinks(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        try {
            return response()->json(
                (new ApiVlessDeepLinksResource($this->users->getVlessWhiteListLinks($user)))->resolve()
            );
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function vlessWhiteListQrCode(Request $request): Response
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
                ->generate($this->users->getVlessWhiteListLink($user));

            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="vless-wl-qrcode.png"');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }

    public function vlessWhiteListSendQr(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($response = $this->ensureActiveAccess($user)) {
            return $response;
        }

        $temporaryPath = null;

        try {
            $png = QrCode::format('png')
                ->margin(5)
                ->size(512)
                ->generate($this->users->getVlessWhiteListLink($user));

            $temporaryPath = $this->storeTemporaryTelegramFile($png, 'vless-wl-qrcode.png');

            Telegram::sendPhoto([
                'chat_id' => (string) $user->telegram_id,
                'photo' => InputFile::create($temporaryPath, 'vless-wl-qrcode.png'),
                'caption' => 'VLESS БС QR-код',
            ]);

            return response()->json([
                'message' => 'QR-код отправлен в бот.',
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => 'Не удалось отправить QR-код в бота. Откройте диалог с ботом и попробуйте ещё раз.',
            ], 422);
        } finally {
            if ($temporaryPath && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
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

    /**
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function resolveWireGuardDocument(Config $config): array
    {
        if (! $config->server->isModernWireGuardType()) {
            return [$config->path, $config->download_filename, null];
        }

        $directory = storage_path('app/tmp/wireguard-downloads');

        File::ensureDirectoryExists($directory);

        $temporaryPath = $directory.'/'.Str::uuid().'.conf';
        $content = WireGuardAgentConfigService::instance($config)->getClientConfig();

        File::put($temporaryPath, $content.PHP_EOL);

        return [$temporaryPath, $config->download_filename, $temporaryPath];
    }

    private function buildWireGuardQrPng(Config $config): string
    {
        $content = $config->server->isModernWireGuardType()
            ? WireGuardAgentConfigService::instance($config)->getClientConfig()
            : $config->getQrCodeContent();

        return QrCode::format('png')
            ->margin(5)
            ->size(512)
            ->generate($content);
    }

    private function storeTemporaryTelegramFile(string $content, string $filename): string
    {
        $directory = storage_path('app/tmp/telegram-mini-app');

        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.Str::uuid().'-'.$filename;
        File::put($path, $content);

        return $path;
    }
}
