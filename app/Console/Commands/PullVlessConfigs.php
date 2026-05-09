<?php

namespace App\Console\Commands;

use App\Entities\VlessConfig;
use App\Models\VlessConfig AS VlessConfigModel;
use App\Models\Server;
use App\Services\XuiConfigService;
use Illuminate\Console\Command;
use Throwable;

class PullVlessConfigs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vless-configs:pull';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Vless configs from servers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $servers = Server::query()
            ->whereNotNull([
                'panel_link',
                'panel_username',
                'panel_password'
            ])
            ->whereIsVless(true)
            ->get();

        foreach ($servers as $server) {
            $this->handleServer($server);
        }

        return self::SUCCESS;
    }

    private function handleServer(Server $server): void
    {
        try {
            $data = (new XuiConfigService($server))->getInbounds();
        } catch (Throwable $exception) {
            report($exception);
            $this->warn("Failed to pull VLESS configs from server [{$server->id}]");
            return;
        }

        $uuids = [];

        foreach ($data as $row) {
            try {
                $settings = $this->decodeJsonField($row['settings'] ?? null);
                $streamSettings = $this->decodeJsonField($row['streamSettings'] ?? $row['stream_settings'] ?? null);
            } catch (Throwable $exception) {
                continue;
            }

            if (($row['protocol'] ?? null) !== 'vless') {
                continue;
            }

            $clients = collect($settings['clients'] ?? []);

            foreach ($clients as $client) {
                $uuid = $client['id'] ?? null;

                if (! $uuid) {
                    continue;
                }

                $config = new VlessConfig(
                    $server->id,
                    null,
                    $client['email'] ?? null,
                    null,
                    true,
                    !empty($client['enable']),
                    $uuid,
                    $client['subId'] ?? null,
                    $row['port'] ?? null,
                    $streamSettings['network'] ?? null,
                    'none',
                    $streamSettings['security'] ?? null,
                    $client['flow'] ?? null,
                    $streamSettings['realitySettings']['settings']['publicKey'] ?? null,
                    $streamSettings['realitySettings']['settings']['fingerprint'] ?? null,
                    $streamSettings['realitySettings']['serverNames'][0] ?? null,
                    $streamSettings['realitySettings']['shortIds'][0] ?? null,
                    '/'
                );

                $vlessConfig = [
                    ...$config->toArray(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                unset($vlessConfig['user_id']);

                $uuids[] = $uuid;

                VlessConfigModel::query()->updateOrCreate([
                    'server_id' => $server->id,
                    'uuid' => $uuid,
                ], $vlessConfig);
            }
        }

        if ($uuids) {
            VlessConfigModel::query()
                ->where('server_id', '=', $server->id)
                ->whereNotIn('uuid', $uuids)
                ->delete();
        }
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
