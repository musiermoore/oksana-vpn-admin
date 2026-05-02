<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class UserController extends Controller
{
    public function balance($telegram)
    {
        $user = UserApiService::instance($telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'message' =>
                    "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler"
            ], 404);
        }

        return response()->json([
            'balance' => max(0, $user->balance),
            'debt' => max(0, -$user->balance)
        ]);
    }

    public function getUserConfigs(string $telegram, string $type)
    {
        $user = UserApiService::instance($telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'configs' => [],
                'message' =>
                    "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler"
            ], 404);
        }

        if (! $user->hasActiveAccess()) {
            return response()->json([
                'configs' => [],
                'type' => 'debt',
                'message' => "VPN не оплачен, необходимо пополнить баланс. Команда /balance"
            ], 403);
        }

        $configs = $type === 'vless'
            ? $user->vlessConfigs
            : $user->configs;

        return response()->json([
            'configs' => $configs,
        ]);
    }

    public function downloadConfig(string $telegram, string $type, string $configId)
    {
        $user = UserApiService::instance($telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'message' => "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler или @soussangler"
            ], 404);
        }

        if (! $user->hasActiveAccess()) {
            return response()->json([
                'type' => 'debt',
                'message' => "VPN не оплачен, необходимо пополнить баланс. Команда /balance"
            ], 403);
        }

        $query = $type === 'vless'
            ? $user->vlessConfigs()
            : $user->configs();

        $config = $query->find($configId);

        if (empty($config)) {
            return response()->json([
                'message' => "Я не смогла найти такой конфиг ☹️\n\n"
                    . "Сообщи об этом @soussangler"
            ], 404);
        }

        try {
            return $type === 'vless'
                ? response($config->getLink())
                : response()->download($config->path, $config->name . '.conf');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => "Что-то пошло не так 🤯️\n\n"
                    . "Сообщи об этом @soussangler" . $exception->getMessage()
            ], 500);
        }
    }

    public function downloadQrCode(string $telegram, string $type, string $configId)
    {
        $user = UserApiService::instance($telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'message' => "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler"
            ], 404);
        }

        if (! $user->hasActiveAccess()) {
            return response()->json([
                'user' => $user,
                'type' => 'debt',
                'message' => "VPN не оплачен, необходимо пополнить баланс. Команда /balance"
            ], 403);
        }

        $query = $type === 'vless'
            ? $user->vlessConfigs()
            : $user->configs();

        $config = $query->find($configId);

        if (empty($config)) {
            return response()->json([
                'message' => "Я не смогла найти такой конфиг ☹️\n\n"
                    . "Сообщи об этом @soussangler"
            ], 404);
        }

        try {
            $configBody = $config->getQrCodeContent();

            $svg = QrCode::margin(5)->size(512)->generate($configBody);

            return response($svg)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Content-Disposition', 'attachment; filename="qrcode.svg"');
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => "Что-то пошло не так 🤯️\n\n"
                    . "Сообщи об этом @soussangler"
            ], 500);
        }
    }

    public function saveTelegramId(Request $request, $telegram)
    {
        User::whereTelegram('@' . $telegram)->update([
            'telegram_id' => $request->telegram_id
        ]);

        User::whereTelegramId($request->telegram_id)
            ->update([
                'telegram' => '@' . $telegram
            ]);

        return response(200);
    }

    public function getVlessLink(Request $request, string $telegram)
    {
        $user = UserApiService::instance($telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'message' => "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler"
            ], 404);
        }

        if (! $user->hasActiveAccess()) {
            return response()->json([
                'user' => $user,
                'type' => 'debt',
                'message' => "VPN не оплачен, необходимо пополнить баланс. Команда /balance"
            ], 403);
        }

        $link = route('vless.connect', [
            'tg' => Crypt::encrypt($user->telegram_id),
            'i' => Crypt::encrypt($user->id),
        ], absolute: false);

        $link = config('vless.domain') . $link;

        return response($link);
    }
}
