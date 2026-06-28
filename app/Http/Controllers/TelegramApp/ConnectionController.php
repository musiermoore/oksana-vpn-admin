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
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

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
                    'send_file_to_bot_url' => route('telegram-app.wireguard.configs.send-file', [
                        'configId' => $config->id,
                    ], absolute: false),
                    'send_qr_to_bot_url' => route('telegram-app.wireguard.configs.send-qr', [
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

    public function wireGuardSendFile(Request $request, int $configId): JsonResponse
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

        $temporaryPath = null;

        try {
            [$path, $filename, $temporaryPath] = $this->resolveWireGuardDocument($config);

            Telegram::sendDocument([
                'chat_id' => (string) $user->telegram_id,
                'document' => InputFile::create($path, $filename),
                'caption' => "WireGuard конфиг: {$config->name}",
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

    public function wireGuardSendQr(Request $request, int $configId): JsonResponse
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

        $temporaryPath = null;

        try {
            $png = $this->buildWireGuardQrPng($config);
            $temporaryPath = $this->storeTemporaryTelegramFile($png, 'wireguard-qrcode.png');

            Telegram::sendPhoto([
                'chat_id' => (string) $user->telegram_id,
                'photo' => InputFile::create($temporaryPath, 'wireguard-qrcode.png'),
                'caption' => "WireGuard QR: {$config->name}",
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
