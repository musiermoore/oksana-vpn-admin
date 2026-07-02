<?php

namespace App\Services;

use App\Models\Server;
use App\Models\UserServerStat;
use App\Models\UserServerStatHistory;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
            return $lock->block(5, function () use ($server): array {
                $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
                $rows = $service->getClientTrafficSummaries();
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
                    $totals[$userId] ??= [
                        'upload_bytes' => 0,
                        'download_bytes' => 0,
                    ];
                    $totals[$userId]['upload_bytes'] += max(0, (int) ($row['upload_bytes'] ?? 0));
                    $totals[$userId]['download_bytes'] += max(0, (int) ($row['download_bytes'] ?? 0));
                }

                foreach ($totals as $userId => $payload) {
                    UserServerStat::query()->updateOrCreate(
                        [
                            'user_id' => $userId,
                            'server_id' => $server->id,
                        ],
                        $payload,
                    );

                    UserServerStatHistory::query()->create([
                        'user_id' => $userId,
                        'server_id' => $server->id,
                        'upload_bytes' => $payload['upload_bytes'],
                        'download_bytes' => $payload['download_bytes'],
                        'collected_at' => now(),
                    ]);
                }

                UserServerStat::query()
                    ->where('server_id', $server->id)
                    ->when(
                        $totals !== [],
                        fn ($query) => $query->whereNotIn('user_id', array_keys($totals)),
                        fn ($query) => $query,
                    )
                    ->delete();

                return array_map('intval', array_keys($totals));
            });
        } catch (LockTimeoutException) {
            Log::warning('Skipped XUI user stats sync because the server lock is busy.', [
                'server_id' => $server->id,
            ]);

            return [];
        }
    }
}
