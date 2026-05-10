<?php

namespace App\Jobs;

use App\Entities\VlessConfig;
use App\Models\Server;
use App\Models\VlessConfig as VlessConfigModel;
use App\Services\XuiConfigService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PullVlessConfigsForServerJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $serverId,
    ) {
        $this->onQueue('vless-configs');
    }

    public function uniqueId(): string
    {
        return (string) $this->serverId;
    }

    public function handle(): void
    {
        $server = Server::query()->find($this->serverId);

        if (! $server) {
            return;
        }

        $uuids = [];

        $data = (new XuiConfigService($server))->getInbounds();

        foreach ($data as $row) {
            $this->handleInbound($row, $server, $uuids);
        }

        if ($uuids !== []) {
            VlessConfigModel::query()
                ->where('server_id', '=', $server->id)
                ->whereNotIn('uuid', $uuids)
                ->delete();
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    private function handleInbound(array $row, Server $server, array &$uuids): void
    {
        try {
            $settings = $this->decodeJsonField($row['settings'] ?? null);
            $streamSettings = $this->decodeJsonField($row['streamSettings'] ?? $row['stream_settings'] ?? null);
        } catch (Throwable) {
            return;
        }

        if (($row['protocol'] ?? null) !== 'vless') {
            return;
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
