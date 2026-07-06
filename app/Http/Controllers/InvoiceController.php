<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\SendInvoiceToTaxRequest;
use App\Http\Requests\Invoice\UpdateInvoiceTaxStatusRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\TaxRequestLogResource;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Repositories\TaxRequestLogRepository;
use App\Services\Tax\InvoiceTaxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly TaxRequestLogRepository $taxLogs,
        private readonly InvoiceTaxService $invoiceTax,
    ) {}

    public function index(Request $request)
    {
        $invoices = $this->invoices->latestWithRelations();

        return $this->inertia('Invoices/Index', [
            'invoices' => InvoiceResource::collection($invoices)->toArray($request),
            'stats' => [
                'total' => $invoices->count(),
                'paid' => $invoices->where('paid', true)->count(),
                'sent_to_tax' => $invoices->where('tax_status', 'sent')->count(),
                'queued_to_tax' => $invoices->where('tax_status', 'queued')->count() + $invoices->where('tax_status', 'sending')->count(),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $invoice = $this->invoices->findWithRelations((int) $invoice->id)
            ?? $invoice->load(['user', 'transactions.type']);
        $logs = $this->taxLogs->latestForInvoice((int) $invoice->id);

        return $this->inertia('Invoices/Show', [
            'invoice' => (new InvoiceResource($invoice))->toArray($request),
            'tax_logs' => TaxRequestLogResource::collection($logs)->toArray($request),
        ]);
    }

    public function edit(Request $request, Invoice $invoice)
    {
        $invoice = $this->invoices->findWithRelations((int) $invoice->id)
            ?? $invoice->load(['user', 'transactions.type']);

        return $this->inertia('Invoices/Edit', [
            'invoice' => (new InvoiceResource($invoice))->toArray($request),
            'tax_status_options' => Invoice::taxStatuses(),
        ]);
    }

    public function sendPreview(Request $request, Invoice $invoice)
    {
        $invoice = $this->invoices->findWithRelations((int) $invoice->id)
            ?? $invoice->load(['user', 'transactions.type']);

        return $this->inertia('Invoices/Send', [
            'invoice' => (new InvoiceResource($invoice))->toArray($request),
        ]);
    }

    public function send(SendInvoiceToTaxRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoiceTax->queueSend($invoice, $request->user()?->id);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->back()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Инвойс поставлен в очередь на отправку в налоговую.');
    }

    public function sendPaid(Request $request): RedirectResponse
    {
        $queued = $this->invoiceTax->queueEligiblePaidInvoices($request->user()?->id);

        return redirect()
            ->route('invoices.index')
            ->with('success', sprintf('В очередь на отправку в налоговую поставлено %d оплаченных инвойсов.', $queued));
    }

    public function updateTaxStatus(UpdateInvoiceTaxStatusRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoiceTax->updateTaxStatus($invoice, $request->toDto()->taxStatus);

        return redirect()
            ->route('invoices.edit', $invoice)
            ->with('success', 'Статус налоговой обновлён вручную.');
    }
}
