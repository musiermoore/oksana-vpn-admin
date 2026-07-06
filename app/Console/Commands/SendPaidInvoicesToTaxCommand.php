<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tax\InvoiceTaxService;
use Illuminate\Console\Command;

class SendPaidInvoicesToTaxCommand extends Command
{
    protected $signature = 'invoices:send-paid-to-tax {--initiator-user-id=}';

    protected $description = 'Queue all eligible paid invoices for sending to tax service';

    public function handle(InvoiceTaxService $invoiceTaxService): int
    {
        $initiatorUserId = $this->option('initiator-user-id');
        $queued = $invoiceTaxService->queueEligiblePaidInvoices(
            $initiatorUserId !== null && $initiatorUserId !== ''
                ? (int) $initiatorUserId
                : null,
        );

        $this->info(sprintf('Queued %d paid invoices for tax sending.', $queued));

        return self::SUCCESS;
    }
}
