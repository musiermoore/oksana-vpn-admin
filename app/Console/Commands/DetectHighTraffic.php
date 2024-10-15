<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\Traffic;
use App\Services\WireGuardService;
use App\Services\WireGuardTrafficService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
        $highLimitInMb = 500;
        $highLimit = $highLimitInMb * 1024 * 1024;

        $configs = Config::query()
            ->with(['user'])
            ->select([
                'configs.*',
                DB::raw($this->getTrafficColumn(WireGuardTrafficService::SENT_TYPE)),
                DB::raw($this->getTrafficColumn(WireGuardTrafficService::RECEIVED_TYPE)),
            ])
            ->groupBy('configs.id')
            ->having(WireGuardTrafficService::SENT_TYPE . '_traffic_usage', '>=', $highLimit)
            ->orHaving(WireGuardTrafficService::RECEIVED_TYPE . '_traffic_usage', '>=', $highLimit)
            ->get();

        $devChatId = "-4543488848";

        foreach ($configs as $config) {
            if ($config['sent_traffic_usage'] > $highLimit) {
                $size = $config['sent_traffic_usage'];
            } elseif ($config['received_traffic_usage'] > $highLimit) {
                $size = $config['received_traffic_usage'];
            } else {
                continue;
            }

            $size = $size / 1024 / 1024;

            $user = $config->user;

            Log::error("Test: " . $user->full_name . " даёт джаззу больше >$highLimitInMb Мбайт. \n\nТрафик за 3 минуты: $size Мбайт");

            Telegram::sendMessage([
                'chat_id' => $devChatId,
                'text' => $user->full_name . " даёт джаззу больше >$highLimitInMb Мбайт. \n\nТрафик за 3 минуты: $size Мбайт"
            ]);
        }
    }

    private function getTrafficColumn($type): string
    {
        $startDate = now()->subMinutes(3);
        $endDate = now();

        $trafficQuery = Traffic::query()
            ->select(['traffic.' . $type])
            ->whereColumn('configs.id', '=', 'traffic.config_id')
            ->whereBetween('traffic.created_at', [$startDate, $endDate])
            ->limit(1);

        $startDateQuery = $trafficQuery->clone()->orderBy('traffic.created_at')->toRawSql();
        $endDateQuery = $trafficQuery->clone()->orderByDesc('traffic.created_at')->toRawSql();

        return "($endDateQuery) - ($startDateQuery) AS {$type}_traffic_usage";
    }
}
