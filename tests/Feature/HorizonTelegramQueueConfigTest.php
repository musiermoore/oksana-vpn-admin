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
}
