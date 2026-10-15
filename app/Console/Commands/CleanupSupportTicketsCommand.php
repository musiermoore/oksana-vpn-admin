<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramApp\SupportTicketMaintenanceService;
use Illuminate\Console\Command;

class CleanupSupportTicketsCommand extends Command
{
    protected $signature = 'support-tickets:cleanup';

    protected $description = 'Закрывает неактивные обращения и удаляет старые вложения.';

    public function handle(SupportTicketMaintenanceService $maintenance): int
    {
        $maintenance->run();

        return self::SUCCESS;
    }
}
