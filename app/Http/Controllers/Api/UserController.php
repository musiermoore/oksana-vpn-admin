<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserApiService;
use Exception;
use Illuminate\Http\Request;
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
                    . "Сообщи свой никнем @soussangler или @musiermoore"
            ], 404);
        }

        return response()->json([
            'balance' => max(0, $user->transactions_sum_amount - $user->payment_amount),
            'debt' => max(0, $user->payment_amount - $user->transactions_sum_amount)
        ]);
    }

    public function getUserConfigs($telegram)
    {
        $user = UserApiService::instance($telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'user' => null,
                'message' =>
                    "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler или @musiermoore"
            ], 404);
        }

        if ($user->hasDebt()) {
            return response()->json([
                'user' => $user,
                'type' => 'debt',
                'message' => "VPN не оплачен, необходимо пополнить баланс. Команда /balance"
            ], 403);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    public function downloadConfig($telegram, $config)
    {
        $user = UserApiService::instance($telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'message' => "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler или @soussangler или @musiermoore"
            ], 404);
        }

        if ($user->hasDebt()) {
            return response()->json([
                'user' => $user,
                'type' => 'debt',
                'message' => "VPN не оплачен, необходимо пополнить баланс. Команда /balance"
            ], 403);
        }

        $config = $user->configs()
            ->where('name', $config)
            ->first();

        if (empty($config)) {
            return response()->json([
                'message' => "Я не смогла найти такой конфиг ☹️\n\n"
                    . "Сообщи об этом @soussangler или @musiermoore"
            ], 404);
        }

        try {
            return response()->download($config->path, $config->name . '.conf');
        } catch (Exception $exception) {
            return response()->json([
                'message' => "Что-то пошло не так 🤯️\n\n"
                    . "Сообщи об этом @soussangler или @musiermoore"
            ], 500);
        }
    }

    public function downloadQrCode($telegram, $config)
    {
        $user = UserApiService::instance($telegram)->getUser();

        if (empty($user)) {
            return response()->json([
                'message' => "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @soussangler или @musiermoore"
            ], 404);
        }

        if ($user->hasDebt()) {
            return response()->json([
                'user' => $user,
                'type' => 'debt',
                'message' => "VPN не оплачен, необходимо пополнить баланс. Команда /balance"
            ], 403);
        }

        $config = $user->configs()
            ->where('name', $config)
            ->first();

        if (empty($config)) {
            return response()->json([
                'message' => "Я не смогла найти такой конфиг ☹️\n\n"
                    . "Сообщи об этом @soussangler или @musiermoore"
            ], 404);
        }

        try {
            $configBody = file_get_contents($config->path);

            $png = QrCode::format('png')->margin(5)->size(512)->generate($configBody);

            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="qrcode.png"');
        } catch (Exception $exception) {
            return response()->json([
                'message' => "Что-то пошло не так 🤯️\n\n"
                    . "Сообщи об этом @soussangler или @musiermoore"
            ], 500);
        }
    }

    public function saveTelegramId(Request $request, $telegram)
    {
        User::whereTelegram('@' . $telegram)->update([
            'telegram_id' => $request->telegram_id
        ]);

        return response(200);
    }
}
