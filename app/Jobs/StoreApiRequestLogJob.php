<?php

namespace App\Jobs;

use App\Models\ApiRequestLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreApiRequestLogJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        ApiRequestLog::query()->create($this->payload);
    }
}
