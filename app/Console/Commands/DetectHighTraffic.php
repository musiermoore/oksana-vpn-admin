<?php

namespace App\Console\Commands;

use App\Services\WireGuardService;
use App\Services\WireGuardTrafficService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class DetectHighTraffic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wireguard:detect-high-traffic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $traffic = WireGuardTrafficService::getTraffic(
            now()->subMinutes(10),
            now()
        );

        $highLimitInMb = 300;
        $highLimit = $highLimitInMb * 1024 * 1024;
        $devChatId = "-4543488848";

        foreach ($traffic as $peer) {
            $size = 0;

            if ($peer['sent'] > $highLimit) {
                $size = $peer['sent'];
            } elseif ($peer['received'] > $highLimit) {
                $size = $peer['received'];
            } else {
                continue;
            }

            $size /= 1024 / 1024;

            $config = $peer->config;
            $user = $config->user;

            Log::error("Test: " . $user->full_name . " даёт джаззу больше >$highLimitInMb Мбайт. \n\nТрафик за 3 минуты: $size Мбайт");

            Telegram::sendMessage([
                'chat_id' => $devChatId,
                'text' => $user->full_name . " даёт джаззу больше >$highLimitInMb Мбайт. \n\nТрафик за 3 минуты: $size Мбайт"
            ]);
        }
    }
}
