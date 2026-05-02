<?php

namespace App\Console\Commands;

use App\Entities\VlessConfig;
use App\Models\VlessConfig AS VlessConfigModel;
use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
    public function handle()
    {
        $servers = Server::whereIsVless(true)->get();

        foreach ($servers as $server) {
            $this->handleServer($server);
        }
    }

    private function handleServer(Server $server)
    {
        $tempKeyPath = tempnam(sys_get_temp_dir(), 'sshkey_');

        $key = $server->ssh_private_key;

        // convert escaped newlines if they exist
        $key = str_replace('\\n', "\n", $key);

        // remove Windows CR
        $key = str_replace("\r", '', $key);

        // ensure newline at end
        $key = trim($key) . "\n";

        file_put_contents($tempKeyPath, $key);
        chmod($tempKeyPath, 0600);

        $sqliteQuery = str_replace('`', '\"', DB::table('inbounds')->toRawSql());
        $sqliteCommand = "sqlite3 /etc/x-ui/x-ui.db -json \"$sqliteQuery\"";

        $command = "timeout 15 $(which ssh) "
            . "-i " . escapeshellarg($tempKeyPath) . " "
            . "-o BatchMode=yes "
            . "-o StrictHostKeyChecking=no "
            . "root@" . escapeshellarg($server->ip) . " "
            . escapeshellarg($sqliteCommand);

        $output = shell_exec($command);

        if (! is_string($output) || trim($output) === '') {
            return;
        }

        $data = json_decode($output, true);

        if (! is_array($data)) {
            return;
        }

        $uuids = [];

        foreach ($data as $row) {
            try {
                $settings = json_decode($row['settings'], true);
                $streamSettings = json_decode($row['stream_settings'], true);
            } catch (\Exception $exception) {
                continue;
            }

            $clients = collect($settings['clients'] ?? [])
                ->filter(fn($client) => !empty($client['enable']));

            foreach ($clients as $client) {
                $config = new VlessConfig(
                    $server->id,
                    null,
                    $client['email'],
                    null,
                    true,
                    $client['id'],
                    $client['subId'] ?? null,
                    $row['port'],
                    $streamSettings['network'],
                    'none',
                    $streamSettings['security'],
                    $client['flow'],
                    $streamSettings['realitySettings']['settings']['publicKey'],
                    $streamSettings['realitySettings']['settings']['fingerprint'],
                    $streamSettings['realitySettings']['serverNames'][0] ?? null,
                    $streamSettings['realitySettings']['shortIds'][0] ?? null,
                '/'
                );

                $vlessConfig = [
                    ...$config->toArray(),
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                unset($vlessConfig['user_id']);

                $uuid = $client['id'] ?? null;

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
}
