<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\VlessConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class PersistPulledVlessInboundJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @param  array<int, array{id:int, attributes:array<string, mixed>}>  $updates
     */
    public function __construct(
        public readonly int $serverId,
        public readonly int $inboundId,
        public readonly array $updates,
    ) {
        $this->onQueue('vless-configs');
    }

    public function handle(): void
    {
        $configIds = collect($this->updates)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $configs = VlessConfig::query()
            ->where('server_id', $this->serverId)
            ->whereKey($configIds)
            ->get()
            ->keyBy(fn (VlessConfig $config): int => (int) $config->getKey());

        $rows = [];
        $updateColumns = [];

        foreach ($this->updates as $update) {
            $configId = (int) ($update['id'] ?? 0);
            $attributes = is_array($update['attributes'] ?? null) ? $update['attributes'] : [];
            $config = $configs->get($configId);

            if (! $config instanceof VlessConfig || $attributes === []) {
                continue;
            }

            $rows[] = [
                ...$config->getAttributes(),
                ...$attributes,
            ];
            $updateColumns = [...$updateColumns, ...array_keys($attributes)];
        }

        if ($rows !== []) {
            VlessConfig::query()->upsert(
                $rows,
                ['id'],
                array_values(array_unique($updateColumns)),
            );
        }
    }
}
