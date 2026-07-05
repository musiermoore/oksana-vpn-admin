<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Tax\InvoiceTaxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class SendInvoiceToTaxJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $invoiceId,
        public readonly ?int $initiatorUserId = null,
    ) {
        $this->onQueue('tax');
    }

    public function handle(InvoiceTaxService $invoiceTax): void
    {
        $invoiceTax->handleSend($this->invoiceId, $this->initiatorUserId);
    }
}
