<?php

namespace App\Services;

use App\Models\Server;
use App\Models\UserServerStat;
use App\Models\UserDailyTraffic;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XuiUserTrafficSyncService
{
    public function __construct(
        private readonly XrayConfigLocatorService $configLocator,
    ) {}

    /**
     * @return array<int, int>
     */
    public function syncServer(Server $server): array
    {
        if (! $server->is_active) {
            return [];
        }

        $lock = Cache::lock('xui-user-stats-sync:'.$server->id, 240);

        try {
            return $lock->block(5, fn (): array => $this->syncLocked($server));
        } catch (LockTimeoutException) {
            Log::warning('Skipped XUI user stats sync because the server lock is busy.', [
                'server_id' => $server->id,
            ]);

            return [];
        }
    }

    /** @return array<int, int> */
    private function syncLocked(Server $server): array
    {
        $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
        $totals = $this->aggregateTotals($server, $service->getClientTrafficSummaries());

        $this->persistTotals($server, $totals);

        return array_map('intval', array_keys($totals));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{upload_bytes:int, download_bytes:int}>
     */
    private function aggregateTotals(Server $server, array $rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));

            if ($email === '') {
                continue;
            }

            $resolved = $this->configLocator->findByServerAndEmail($server, $email);

            if ($resolved === null) {
                Log::warning('Skipping unknown client traffic row during sync.', [
                    'server_id' => $server->id,
                    'email' => $email,
                ]);

                continue;
            }

            /** @var Model&object{user_id:int|null} $config */
            $config = $resolved['config'];

            if (empty($config->user_id)) {
                continue;
            }

            $userId = (int) $config->user_id;
            $totals[$userId] ??= ['upload_bytes' => 0, 'download_bytes' => 0];
            $totals[$userId]['upload_bytes'] += max(0, (int) ($row['upload_bytes'] ?? 0));
            $totals[$userId]['download_bytes'] += max(0, (int) ($row['download_bytes'] ?? 0));
        }

        return $totals;
    }

    /** @param array<int, array{upload_bytes:int, download_bytes:int}> $totals */
    private function persistTotals(Server $server, array $totals): void
    {
        $collectedAt = now();

        DB::transaction(function () use ($server, $totals, $collectedAt): void {
            $previousStats = $totals === []
                ? collect()
                : UserServerStat::query()
                    ->where('server_id', $server->id)
                    ->whereIn('user_id', array_keys($totals))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('user_id');
            $statRows = [];
            $dailyRows = [];

            foreach ($totals as $userId => $payload) {
                $previous = $previousStats->get($userId);
                $uploadDelta = $previous === null
                    ? 0
                    : $this->trafficDelta((int) $previous->upload_bytes, $payload['upload_bytes']);
                $downloadDelta = $previous === null
                    ? 0
                    : $this->trafficDelta((int) $previous->download_bytes, $payload['download_bytes']);

                $statRows[] = [
                    'user_id' => $userId,
                    'server_id' => $server->id,
                    'upload_bytes' => $payload['upload_bytes'],
                    'download_bytes' => $payload['download_bytes'],
                    'created_at' => $collectedAt,
                    'updated_at' => $collectedAt,
                ];

                if ($uploadDelta === 0 && $downloadDelta === 0) {
                    continue;
                }

                $dailyRows[] = [
                    'user_id' => $userId,
                    'traffic_date' => $collectedAt->toDateString(),
                    'upload_bytes' => $uploadDelta,
                    'download_bytes' => $downloadDelta,
                    'created_at' => $collectedAt,
                    'updated_at' => $collectedAt,
                ];
            }

            if ($statRows !== []) {
                UserServerStat::query()->upsert(
                    $statRows,
                    ['user_id', 'server_id'],
                    ['upload_bytes', 'download_bytes', 'updated_at'],
                );
            }

            if ($dailyRows !== []) {
                UserDailyTraffic::query()->upsert(
                    $dailyRows,
                    ['user_id', 'traffic_date'],
                    [
                        'upload_bytes' => DB::raw('upload_bytes + VALUES(upload_bytes)'),
                        'download_bytes' => DB::raw('download_bytes + VALUES(download_bytes)'),
                        'updated_at' => DB::raw('VALUES(updated_at)'),
                    ],
                );
            }
        });
    }

    private function trafficDelta(int $previous, int $current): int
    {
        return $current >= $previous ? $current - $previous : $current;
    }
}
