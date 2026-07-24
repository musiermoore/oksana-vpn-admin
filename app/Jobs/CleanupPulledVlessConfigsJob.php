<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\VlessConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class CleanupPulledVlessConfigsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<int, int>  $retainedConfigIds
     */
    public function __construct(
        public readonly int $serverId,
        public readonly array $retainedConfigIds,
    ) {
        $this->onQueue('vless-configs');
    }

    public function handle(): void
    {
        if ($this->retainedConfigIds === []) {
            return;
        }

        VlessConfig::query()
            ->where('server_id', $this->serverId)
            ->whereNull('user_id')
            ->whereNotIn('id', $this->retainedConfigIds)
            ->delete();
    }
}
