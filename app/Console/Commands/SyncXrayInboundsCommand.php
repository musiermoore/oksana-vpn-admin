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
        $existingExternalIds = XrayInbound::query()
            ->where('server_id', $server->id)
            ->pluck('external_id')
            ->mapWithKeys(fn (mixed $externalId): array => [(int) $externalId => true]);
        $nextSortOrder = (int) (XrayInbound::query()
            ->where('server_id', $server->id)
            ->max('sort_order') ?? -1) + 1;
        $timestamp = now();
        $rows = [];

        foreach ($inbounds as $inbound) {
            if (! is_array($inbound)) {
                continue;
            }

            $externalId = (int) ($inbound['id'] ?? 0);

            if ($externalId < 1 || ! $this->hasPersistableParams($service->normalizeInbound($inbound))) {
                continue;
            }

            $syncedExternalIds->push($externalId);
            $rows[] = [
                'server_id' => $server->id,
                'external_id' => $externalId,
                'sort_order' => $existingExternalIds->has($externalId) ? 0 : $nextSortOrder++,
                'params' => json_encode($this->snapshotParams($inbound), JSON_THROW_ON_ERROR),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows !== []) {
            XrayInbound::query()->upsert(
                $rows,
                ['server_id', 'external_id'],
                ['params', 'updated_at'],
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
     * The panel's client list is duplicated in vless_configs and can be very large.
     * Keep only the inbound definition needed by the application snapshot.
     *
     * @param  array<string, mixed>  $inbound
     * @return array<string, mixed>
     */
    private function snapshotParams(array $inbound): array
    {
        $settings = $inbound['settings'] ?? null;

        if (is_array($settings)) {
            unset($settings['clients']);
            $inbound['settings'] = $settings;
        } elseif (is_string($settings)) {
            $decodedSettings = json_decode($settings, true);

            if (is_array($decodedSettings)) {
                unset($decodedSettings['clients']);
                $inbound['settings'] = $decodedSettings;
            }
        }

        return $inbound;
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
