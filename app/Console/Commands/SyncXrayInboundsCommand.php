<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\XrayInbound;
use App\Services\XuiConfigServiceFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class SyncXrayInboundsCommand extends Command
{
    protected $signature = 'xray-inbounds:sync';

    protected $description = 'Sync Xray inbound records from 3x-ui panels';

    public function handle(): int
    {
        $servers = Server::query()
            ->vless()
            ->orderBy('id')
            ->get();

        foreach ($servers as $server) {
            $this->syncPanelInbounds($server);
        }

        return self::SUCCESS;
    }

    private function syncPanelInbounds(Server $server): void
    {
        if (! $server->is_active || ! $server->is_ready) {
            return;
        }

        if (! $server->panel_link || ! $server->panel_username || ! $server->panel_password) {
            return;
        }

        try {
            $service = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server);
            $inbounds = $service->getInbounds();
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $syncedExternalIds = collect();

        foreach ($inbounds as $inbound) {
            if (! is_array($inbound)) {
                continue;
            }

            $externalId = (int) ($inbound['id'] ?? 0);

            if ($externalId < 1 || ! $this->hasPersistableParams($service->normalizeInbound($inbound))) {
                continue;
            }

            $syncedExternalIds->push($externalId);

            XrayInbound::query()->updateOrCreate(
                [
                    'server_id' => $server->id,
                    'external_id' => $externalId,
                ],
                [
                    'params' => $inbound,
                ],
            );
        }

        $this->markMissingInboundsAsInactive($server, $syncedExternalIds);
    }

    /**
     * @param  array<string, mixed>  $normalizedInbound
     */
    private function hasPersistableParams(array $normalizedInbound): bool
    {
        return $normalizedInbound['settings'] !== []
            || $normalizedInbound['stream_settings'] !== [];
    }

    /**
     * @param  Collection<int, int>  $syncedExternalIds
     */
    private function markMissingInboundsAsInactive(Server $server, Collection $syncedExternalIds): void
    {
        $query = XrayInbound::query()
            ->where('server_id', $server->id);

        if ($syncedExternalIds->isNotEmpty()) {
            $query->whereNotIn('external_id', $syncedExternalIds->unique()->values()->all());
        }

        $query->update([
            'is_active' => false,
            'params' => null,
        ]);
    }
}
