<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class UserController extends Controller
{
    public function getUserConfigs($telegram)
    {
        $user = $this->getUser($telegram);

        if (empty($user)) {
            return response()->json([
                'user' => null,
                'message' =>
                    "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @musiermoore"
            ], 404);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    public function downloadConfig($telegram, $config)
    {
        $user = $this->getUser($telegram);

        if (empty($user)) {
            return response()->json([
                'message' => "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @musiermoore"
            ], 404);
        }

        $config = $user->configs
            ->where('name', $config)
            ->first();

        if (empty($config)) {
            return response()->json([
                'message' => "Я не смогла найти такой конфиг ☹️\n\n"
                    . "Сообщи об этом @musiermoore"
            ], 404);
        }

        try {
            return response()->download($config->path, $config->name . '.conf');
        } catch (Exception $exception) {
            return response()->json([
                'message' => "Что-то пошло не так 🤯️\n\n"
                    . "Сообщи об этом @musiermoore"
            ], 500);
        }
    }

    public function downloadQrCode($telegram, $config)
    {
        $user = $this->getUser($telegram);

        if (empty($user)) {
            return response()->json([
                'message' => "Я не вижу тебя в списках 😢\n\n"
                    . "Сообщи свой никнем @musiermoore"
            ], 404);
        }

        $config = $user->configs
            ->where('name', $config)
            ->first();

        if (empty($config)) {
            return response()->json([
                'message' => "Я не смогла найти такой конфиг ☹️\n\n"
                    . "Сообщи об этом @musiermoore"
            ], 404);
        }

        try {
            $configBody = file_get_contents($config->path);

            $png = QrCode::format('png')->margin(5)->size(512)->generate($configBody);
            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'attachment; filename="qrcode.png"');;
        } catch (Exception $exception) {
            return response()->json([
                'message' => "Что-то пошло не так 🤯️\n\n"
                    . "Сообщи об этом @musiermoore"
            ], 500);
        }
    }

    private function getUser($telegram)
    {
        return User::query()
            ->with([
                'configs' => function ($query) {
                    $query->select([
                        'id', 'user_id', 'name'
                    ]);
                }
            ])
            ->select([
                'id', 'telegram'
            ])
            ->whereTelegram('@' . $telegram)
            ->first();
    }
}
