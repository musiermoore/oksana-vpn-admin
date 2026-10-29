<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class HorizonTelegramQueueConfigTest extends TestCase
{
    public function test_horizon_listens_to_telegram_queue(): void
    {
        $this->assertContains('telegram', config('horizon.defaults.supervisor-default.queue'));
        $this->assertContains('telegram', config('horizon.environments.production.supervisor-default.queue'));
        $this->assertContains('telegram', config('horizon.environments.local.supervisor-default.queue'));
        $this->assertSame(60, config('horizon.waits.redis:telegram'));
    }

    public function test_horizon_limits_production_worker_processes_to_six(): void
    {
        $production = config('horizon.environments.production');

        $this->assertSame([
            'supervisor-default',
            'supervisor-slow',
            'supervisor-server-console',
        ], array_keys($production));
        $this->assertSame(6, collect($production)->sum('maxProcesses'));
    }
}
