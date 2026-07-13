<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\VlessConfig as VlessConfigModel;
use App\Services\XuiConfigService;
use App\Services\XuiConfigServiceFactory;
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

        if (! $server || ! $server->is_active) {
            return;
        }

        $localConfigIds = [];

        $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
        $data = $service->getAllVlessInbounds();
        $clientIndex = $this->buildClientIndex($service->getClientListEntries());

        foreach ($data as $row) {
            $this->handleInbound($row, $server, $localConfigIds, $service, $clientIndex);
        }

        if ($localConfigIds !== []) {
            VlessConfigModel::query()
                ->where('server_id', '=', $server->id)
                ->whereNotIn('id', $localConfigIds)
                ->delete();
        }
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    private function handleInbound(
        array $row,
        Server $server,
        array &$localConfigIds,
        XuiConfigService $service,
        array $clientIndex,
    ): void
    {
        $settings = $row['settings'] ?? [];

        $clients = collect($settings['clients'] ?? []);

        foreach ($clients as $client) {
            $uuid = $client['id'] ?? $client['uuid'] ?? null;
            $protocol = mb_strtolower((string) ($row['protocol'] ?? ''));

            if (! $uuid && ($protocol !== 'wireguard' || blank($client['email'] ?? null))) {
                continue;
            }

            $mergedClient = $this->mergeClientPayload(
                inboundId: (int) ($row['id'] ?? 0),
                inboundClient: is_array($client) ? $client : [],
                clientIndex: $clientIndex,
            );

            $vlessConfig = [
                ...$service->buildLocalConfigAttributes($row, [
                    'email' => $mergedClient['email'] ?? null,
                    'enable' => ! empty($mergedClient['enable']),
                    'id' => $uuid,
                    'subId' => $mergedClient['subId'] ?? null,
                    'password' => $mergedClient['password'] ?? null,
                    'auth' => $mergedClient['auth'] ?? null,
                    'flow' => $mergedClient['flow'] ?? null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            unset($vlessConfig['user_id']);

            $config = VlessConfigModel::query()->updateOrCreate(
                $this->resolveLookupAttributes($server, $row, $mergedClient, $vlessConfig),
                $vlessConfig,
            );

            $localConfigIds[] = (int) $config->getKey();
        }
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function resolveLookupAttributes(Server $server, array $inbound, array $client, array $attributes): array
    {
        if (mb_strtolower((string) ($inbound['protocol'] ?? '')) !== 'wireguard' && filled($attributes['uuid'] ?? null)) {
            return [
                'server_id' => $server->id,
                'uuid' => $attributes['uuid'],
            ];
        }

        return [
            'server_id' => $server->id,
            'inbound_id' => (int) ($inbound['id'] ?? 0),
            'name' => (string) ($attributes['name'] ?? $client['email'] ?? ''),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function buildClientIndex(array $rows): array
    {
        $index = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $inboundIds = is_array($row['inboundIds'] ?? null) ? $row['inboundIds'] : [];
            $identifiers = array_filter([
                $row['uuid'] ?? $row['id'] ?? null,
                $row['email'] ?? null,
                $row['subId'] ?? null,
                $row['publicKey'] ?? $row['public_key'] ?? null,
                $row['privateKey'] ?? $row['private_key'] ?? null,
            ], fn (mixed $value) => is_string($value) ? trim($value) !== '' : ! empty($value));

            foreach ($inboundIds as $inboundId) {
                foreach ($identifiers as $identifier) {
                    $index[$this->clientIndexKey((int) $inboundId, (string) $identifier)] = $row;
                }
            }
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $inboundClient
     * @param  array<string, array<string, mixed>>  $clientIndex
     * @return array<string, mixed>
     */
    private function mergeClientPayload(int $inboundId, array $inboundClient, array $clientIndex): array
    {
        $matchedClient = null;

        foreach ([
            $inboundClient['id'] ?? null,
            $inboundClient['uuid'] ?? null,
            $inboundClient['email'] ?? null,
            $inboundClient['subId'] ?? null,
        ] as $identifier) {
            if (! is_scalar($identifier) || trim((string) $identifier) === '') {
                continue;
            }

            $matchedClient = $clientIndex[$this->clientIndexKey($inboundId, (string) $identifier)] ?? null;

            if (is_array($matchedClient)) {
                break;
            }
        }

        return [
            ...$inboundClient,
            ...(is_array($matchedClient) ? $matchedClient : []),
        ];
    }

    private function clientIndexKey(int $inboundId, string $identifier): string
    {
        return $inboundId.':'.$identifier;
    }

}
