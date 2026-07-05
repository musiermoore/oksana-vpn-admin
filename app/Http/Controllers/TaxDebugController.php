<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TaxDebug\ExecuteTaxDebugRequest;
use App\Http\Resources\TaxRequestLogResource;
use App\Models\Invoice;
use App\Repositories\TaxRequestLogRepository;
use App\Services\Tax\TaxDebugService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class TaxDebugController extends Controller
{
    public function __construct(
        private readonly TaxRequestLogRepository $logs,
        private readonly TaxDebugService $taxDebug,
    ) {}

    public function index(Request $request): Response
    {
        $logs = $this->logs->latestWithInvoice(20);

        $latestPaidInvoiceId = Invoice::query()
            ->where('paid', true)
            ->latest('paid_at')
            ->value('id');

        return $this->inertia('TaxDebug/Index', [
            'presets' => [
                ['value' => 'auth', 'label' => 'Auth', 'action' => 'Auth by INN/password', 'method' => 'POST', 'endpoint' => '/auth/lkfl'],
                ['value' => 'user', 'label' => 'User', 'action' => 'Get current user', 'method' => 'GET', 'endpoint' => '/user'],
                ['value' => 'income', 'label' => 'Income / Receipts', 'action' => 'Create income receipt - individual', 'method' => 'POST', 'endpoint' => '/income'],
            ],
            'initial_form' => [
                'preset' => old('preset', 'auth'),
                'invoice_id' => (int) old('invoice_id', $latestPaidInvoiceId ?? 0),
            ],
            'logs' => TaxRequestLogResource::collection($logs)->toArray($request),
            'invoices' => Invoice::query()
                ->latest()
                ->limit(30)
                ->get(['id', 'amount', 'paid', 'tax_status'])
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'label' => sprintf('#%d · %.2f ₽ · %s · tax:%s', $invoice->id, (float) $invoice->amount, $invoice->paid ? 'paid' : 'unpaid', $invoice->tax_status),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function execute(ExecuteTaxDebugRequest $request): RedirectResponse
    {
        $this->taxDebug->queue($request->toDto());

        return redirect()
            ->route('tax-debug.index')
            ->with('success', 'Налоговый debug-запрос поставлен в очередь.');
    }
}
