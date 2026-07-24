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
        foreach ($this->updates as $update) {
            $configId = (int) ($update['id'] ?? 0);
            $attributes = is_array($update['attributes'] ?? null) ? $update['attributes'] : [];

            if ($configId < 1 || $attributes === []) {
                continue;
            }

            VlessConfig::query()
                ->whereKey($configId)
                ->where('server_id', $this->serverId)
                ->update($attributes);
        }
    }
}
