<?php

namespace App\Console\Commands;

use App\Models\Config;
use App\Models\Traffic;
use App\Services\WireGuardTrafficService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
        $highLimitInMb = 750;
        $highLimit = $highLimitInMb * 1024 * 1024;

        $configs = Config::query()
            ->with(['user'])
            ->select([
                'configs.*',
                DB::raw($this->getTrafficColumn(WireGuardTrafficService::SENT_TYPE)),
                DB::raw($this->getTrafficColumn(WireGuardTrafficService::RECEIVED_TYPE)),
            ])
            ->leftJoin('high_traffic_logs', 'high_traffic_logs.config_id', '=', 'configs.id')
            ->whereDoesntHave('highTrafficLogs', function ($query) {
                $query->where('created_at', '>', now()->subMinutes(5));
            })
            ->groupBy('configs.id')
            ->having(WireGuardTrafficService::SENT_TYPE . '_traffic_usage', '>=', $highLimit)
            ->orHaving(WireGuardTrafficService::RECEIVED_TYPE . '_traffic_usage', '>=', $highLimit)
            ->get();

        $devChatId = config('services.telegram.dev_chat_id');

        foreach ($configs as $config) {
            if ($config['sent_traffic_usage'] > $highLimit) {
                $size = $config['sent_traffic_usage'];
                $type = WireGuardTrafficService::SENT_TYPE;
            } elseif ($config['received_traffic_usage'] > $highLimit) {
                $size = $config['received_traffic_usage'];
                $type = WireGuardTrafficService::RECEIVED_TYPE;
            } else {
                continue;
            }

            $config->highTrafficLogs()->create([
                'type' => $type,
                'amount' => $size
            ]);

            $size = round($size / 1024 / 1024, 2);

            $user = $config->user;

            if ($user->is_admin) {
                continue;
            }

            if (!empty($devChatId)) {
                Telegram::sendMessage([
                    'chat_id' => $devChatId,
                    'text' => $user->full_name . " ($config->name) даёт джаззу больше $highLimitInMb Мбайт. \n\nТрафик за 3 минуты: $size Мбайт"
                ]);
            }

            if (!empty($user->telegram_id)) {
                Telegram::sendMessage([
                    'chat_id' => $user->telegram_id,
                    'text' => "Привет! Ты используешь слишком много трафика. "
                        . "Проверь, вдруг у тебя что-то качается. Конфиг: $config->name"
                ]);
            }
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
