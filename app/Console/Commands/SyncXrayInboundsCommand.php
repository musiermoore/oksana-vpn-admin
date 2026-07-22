<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\XrayInbound;
use App\Services\XuiConfigServiceFactory;
use Illuminate\Console\Command;
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
}
