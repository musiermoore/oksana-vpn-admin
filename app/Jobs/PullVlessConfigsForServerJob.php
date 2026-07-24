<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Server;
use App\Models\VlessConfig;
use App\Services\XuiConfigService;
use App\Services\XuiConfigServiceFactory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

class PullVlessConfigsForServerJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

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

        $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
        $inbounds = $service->getAllVlessInbounds();
        $clientIndex = $this->buildClientIndex($service->getClientListEntries());
        $existingConfigIndex = $this->buildExistingConfigIndex($server);
        $retainedConfigIds = [];
        $jobs = [];

        foreach ($inbounds as $row) {
            $updates = $this->buildInboundUpdates(
                row: $row,
                server: $server,
                service: $service,
                clientIndex: $clientIndex,
                existingConfigIndex: $existingConfigIndex,
                retainedConfigIds: $retainedConfigIds,
            );

            if ($updates === []) {
                continue;
            }

            $jobs[] = new PersistPulledVlessInboundJob(
                serverId: $server->id,
                inboundId: (int) ($row['id'] ?? 0),
                updates: $updates,
            );
        }

        if ($retainedConfigIds !== []) {
            $jobs[] = new CleanupPulledVlessConfigsJob(
                serverId: $server->id,
                retainedConfigIds: array_values(array_unique($retainedConfigIds)),
            );
        }

        if ($jobs === []) {
            return;
        }

        if (app()->runningUnitTests() || config('queue.default') === 'sync') {
            foreach ($jobs as $job) {
                Bus::dispatchSync($job);
            }

            return;
        }

        Bus::chain($jobs)
            ->onQueue('vless-configs')
            ->dispatch();
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    /**
     * @param  array<string, array<string, mixed>>  $clientIndex
     * @param  array<string, VlessConfig>  $existingConfigIndex
     * @param  array<int, int>  $retainedConfigIds
     * @return array<int, array{id:int, attributes:array<string, mixed>}>
     */
    private function buildInboundUpdates(
        array $row,
        Server $server,
        XuiConfigService $service,
        array $clientIndex,
        array $existingConfigIndex,
        array &$retainedConfigIds,
    ): array {
        $settings = $row['settings'] ?? [];
        $clients = collect($settings['clients'] ?? []);
        $updates = [];

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

            $vlessConfig = $service->buildLocalConfigAttributes($row, [
                ...$mergedClient,
                'email' => $mergedClient['email'] ?? null,
                'enable' => ! array_key_exists('enable', $mergedClient) || $mergedClient['enable'],
                'id' => $uuid,
                'subId' => $mergedClient['subId'] ?? null,
                'password' => $mergedClient['password'] ?? null,
                'auth' => $mergedClient['auth'] ?? null,
                'flow' => $mergedClient['flow'] ?? null,
            ]);

            $lookupAttributes = $this->resolveLookupAttributes($server, $row, $mergedClient, $vlessConfig);
            $existingConfig = $existingConfigIndex[$this->existingConfigIndexKey($lookupAttributes)] ?? null;

            if (! $existingConfig instanceof VlessConfig) {
                continue;
            }

            $retainedConfigIds[] = (int) $existingConfig->getKey();
            unset(
                $vlessConfig['inbound_id'],
                $vlessConfig['user_id'],
                $vlessConfig['created_at'],
            );
            $updates[] = [
                'id' => (int) $existingConfig->getKey(),
                'attributes' => [
                    ...$vlessConfig,
                    'updated_at' => now(),
                ],
            ];
        }

        return $updates;
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
            'xray_inbound_id' => $attributes['xray_inbound_id'] ?? null,
            'name' => (string) ($attributes['name'] ?? $client['email'] ?? ''),
        ];
    }

    /**
     * @return array<string, VlessConfig>
     */
    private function buildExistingConfigIndex(Server $server): array
    {
        $index = [];

        VlessConfig::query()
            ->where('server_id', $server->id)
            ->whereNotNull('user_id')
            ->get()
            ->each(function (VlessConfig $config) use (&$index): void {
                if (filled($config->uuid)) {
                    $index[$this->existingConfigIndexKey([
                        'server_id' => (int) $config->server_id,
                        'uuid' => (string) $config->uuid,
                    ])] = $config;
                }

                $index[$this->existingConfigIndexKey([
                    'server_id' => (int) $config->server_id,
                    'xray_inbound_id' => $config->xray_inbound_id !== null ? (int) $config->xray_inbound_id : null,
                    'name' => (string) $config->name,
                ])] = $config;
            });

        return $index;
    }

    /**
     * @param  array<string, mixed>  $lookupAttributes
     */
    private function existingConfigIndexKey(array $lookupAttributes): string
    {
        if (array_key_exists('uuid', $lookupAttributes)) {
            return sprintf(
                'uuid:%d:%s',
                (int) ($lookupAttributes['server_id'] ?? 0),
                (string) ($lookupAttributes['uuid'] ?? ''),
            );
        }

        return sprintf(
            'inbound:%d:%s:%s',
            (int) ($lookupAttributes['server_id'] ?? 0),
            $lookupAttributes['xray_inbound_id'] === null ? 'null' : (string) (int) $lookupAttributes['xray_inbound_id'],
            (string) ($lookupAttributes['name'] ?? ''),
        );
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
