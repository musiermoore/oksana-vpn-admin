<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ProxyGroupConfigurationRun;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ProxyGroupConfigurationUpdated implements ShouldBroadcastNow
{
    public function __construct(private readonly ProxyGroupConfigurationRun $run) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('proxy-group-configuration.'.$this->run->id)];
    }

    public function broadcastAs(): string
    {
        return 'proxy-group.configuration.updated';
    }

    public function broadcastWith(): array
    {
        return $this->run->toStatusArray();
    }
}
