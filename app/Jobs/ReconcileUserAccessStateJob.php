<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class ReconcileUserAccessStateJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly int $userId,
    ) {
        $this->onQueue('configs');
    }

    public function handle(): void
    {
        Artisan::call('configs:disable-overdue-debtors', [
            'user_id' => $this->userId,
        ]);
    }
}
