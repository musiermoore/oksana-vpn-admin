<?php

namespace App\Jobs;

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

        $service = new XuiConfigService($server);
        $data = $service->getAllVlessInbounds();

        foreach ($data as $row) {
            $this->handleInbound($row, $server, $uuids, $service);
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

    private function handleInbound(array $row, Server $server, array &$uuids, XuiConfigService $service): void
    {
        $settings = $row['settings'] ?? [];

        $clients = collect($settings['clients'] ?? []);

        foreach ($clients as $client) {
            $uuid = $client['id'] ?? null;

            if (! $uuid) {
                continue;
            }

            $vlessConfig = [
                ...$service->buildLocalConfigAttributes($row, [
                    'email' => $client['email'] ?? null,
                    'enable' => ! empty($client['enable']),
                    'id' => $uuid,
                    'subId' => $client['subId'] ?? null,
                    'flow' => $client['flow'] ?? null,
                ]),
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

}
