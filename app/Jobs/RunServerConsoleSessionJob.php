<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ServerConsoleOutput;
use App\Models\ServerConsoleSession;
use App\Services\ServerConsole\ServerConsoleSessionRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunServerConsoleSessionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 930;

    public int $tries = 1;

    public function __construct(public readonly int $sessionId)
    {
        $this->onConnection('redis');
        $this->onQueue('server-console');
        $this->delay(now()->addSecond());
    }

    public function handle(ServerConsoleSessionRunner $runner): void
    {
        $session = ServerConsoleSession::query()->with('server')->find($this->sessionId);

        if ($session === null) {
            return;
        }

        $runner->run($session);
    }

    public function failed(Throwable $exception): void
    {
        $session = ServerConsoleSession::query()->find($this->sessionId);
        if ($session !== null) {
            $session->update(['status' => 'failed', 'busy' => false]);
            broadcast(new ServerConsoleOutput($session->uuid, status: 'failed', error: 'SSH connection failed. Check the server address and credentials.'));
        }
    }
}
