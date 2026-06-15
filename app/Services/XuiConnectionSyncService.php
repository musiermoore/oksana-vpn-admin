<?php

namespace App\Services;

use App\Models\ActiveConnection;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class XuiConnectionSyncService
{
    public function __construct(
        private readonly XrayConfigLocatorService $configLocator,
    ) {}

    /**
     * @return array<int, int>
     */
    public function syncServer(Server $server): array
    {
        $lock = Cache::lock('xui-connections-sync:'.$server->id, 50);

        return $lock->block(5, function () use ($server): array {
            $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
            $rows = collect($service->getOnlineClientEntries());
            $touchedUserIds = [];

            $rows->groupBy(fn (array $row) => $this->buildGroupingKey($row))
                ->each(function (Collection $group) use ($server, &$touchedUserIds): void {
                    $first = $group->first();
                    $email = trim((string) ($first['email'] ?? ''));
                    $ip = trim((string) ($first['ip'] ?? ''));

                    if ($email === '' || $ip === '') {
                        return;
                    }

                    $resolved = $this->configLocator->findByServerAndEmail($server, $email);

                    if ($resolved === null) {
                        Log::warning('Skipping unknown online 3x-ui client during sync.', [
                            'server_id' => $server->id,
                            'email' => $email,
                            'ip' => $ip,
                        ]);

                        return;
                    }

                    /** @var Model&object{user_id:int|null} $config */
                    $config = $resolved['config'];

                    if (empty($config->user_id)) {
                        return;
                    }

                    $timestamps = $this->resolveSeenTimestamps($group);

                    $connection = ActiveConnection::query()->firstOrNew([
                        'server_id' => $server->id,
                        'config_type' => $resolved['type'],
                        'config_id' => $config->getKey(),
                        'ip' => $ip,
                    ]);

                    $connection->fill([
                        'user_id' => $config->user_id,
                        'protocol' => $resolved['protocol'],
                        'first_seen' => $connection->exists ? $connection->first_seen : $timestamps['first_seen'],
                        'last_seen' => $timestamps['last_seen'],
                    ]);
                    $connection->save();

                    $touchedUserIds[$config->user_id] = $config->user_id;
                });

            return array_values($touchedUserIds);
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     * @return array{first_seen: Carbon, last_seen: Carbon}
     */
    private function resolveSeenTimestamps(Collection $group): array
    {
        $firstSeen = $group
            ->map(fn (array $row) => $this->normalizeTimestamp($row['first_seen'] ?? null))
            ->filter()
            ->sort()
            ->first();

        $lastSeen = $group
            ->map(fn (array $row) => $this->normalizeTimestamp($row['last_seen'] ?? null))
            ->filter()
            ->sort()
            ->last();

        return [
            'first_seen' => $firstSeen ?? now(),
            'last_seen' => $lastSeen ?? now(),
        ];
    }

    private function normalizeTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildGroupingKey(array $row): string
    {
        return trim((string) ($row['email'] ?? ''))
            .'|'
            .trim((string) ($row['ip'] ?? ''));
    }
}
