<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\VlessConfig;
use App\Models\XrayInbound;
use App\Services\XuiConfigServiceFactory;
use Illuminate\Console\Command;
use Throwable;

class SyncXrayInboundsCommand extends Command
{
    protected $signature = 'xray-inbounds:sync';

    protected $description = 'Sync Xray inbound records from legacy ids and 3x-ui panels';

    public function handle(): int
    {
        $servers = Server::query()
            ->vless()
            ->orderBy('id')
            ->get();

        foreach ($servers as $server) {
            $this->syncLegacyInboundIds($server);
            $this->syncPanelInbounds($server);
            $this->syncConfigRelations($server);
        }

        return self::SUCCESS;
    }

    private function syncLegacyInboundIds(Server $server): void
    {
        $inboundIds = collect([
            $server->getAttribute('inbound_id'),
            ...$server->getAllowedInboundIds(),
        ])
            ->map(fn (mixed $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        foreach ($inboundIds as $externalId) {
            XrayInbound::query()->firstOrCreate(
                [
                    'server_id' => $server->id,
                    'external_id' => $externalId,
                ],
                [
                    'is_active' => true,
                    'is_public' => true,
                ],
            );
        }
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
            $inbounds = XuiConfigServiceFactory::make($server->getPanelApiVersion(), $server)->getInbounds();
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        foreach ($inbounds as $inbound) {
            $externalId = (int) ($inbound['id'] ?? 0);

            if ($externalId < 1) {
                continue;
            }

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
    }

    private function syncConfigRelations(Server $server): void
    {
        $inboundIds = XrayInbound::query()
            ->where('server_id', $server->id)
            ->pluck('id', 'external_id');

        VlessConfig::query()
            ->where('server_id', $server->id)
            ->whereNotNull('inbound_id')
            ->get()
            ->each(function (VlessConfig $config) use ($inboundIds): void {
                $xrayInboundId = $inboundIds->get((int) $config->inbound_id);

                if ($xrayInboundId === null || (int) $config->xray_inbound_id === (int) $xrayInboundId) {
                    return;
                }

                $config->update([
                    'xray_inbound_id' => (int) $xrayInboundId,
                ]);
            });
    }
}
