<?php

namespace App\Jobs;

use App\Models\Server;
use App\Services\WireGuardAgentServerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class InstallWireGuardAgentForServerJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $serverId,
    ) {
        $this->onQueue('servers');
    }

    public function handle(): void
    {
        $server = Server::query()->find($this->serverId);

        if (! $server || ! $server->isModernWireGuardType()) {
            return;
        }

        WireGuardAgentServerService::instance($server)->installOrFail();
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
