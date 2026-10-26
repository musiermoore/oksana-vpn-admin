<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ServerConsoleSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ServerConsoleOutput implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $sessionUuid,
        public readonly string $output = '',
        public readonly ?string $status = null,
        public readonly ?string $error = null,
        public readonly ?bool $busy = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('server-console.'.$this->sessionUuid)];
    }

    public function broadcastAs(): string
    {
        return 'console.output';
    }

    public function broadcastWith(): array
    {
        return [
            'output' => $this->output,
            'status' => $this->status,
            'error' => $this->error,
            'busy' => $this->busy,
        ];
    }
}
