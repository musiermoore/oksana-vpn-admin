<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Jobs\SendInvoiceToTaxJob;
use App\Models\Invoice;
use App\Models\TaxRequestLog;
use App\Models\TaxSetting;
use App\Repositories\InvoiceRepository;
use App\Repositories\TaxRequestLogRepository;
use RuntimeException;
use Throwable;

class InvoiceTaxService
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly TaxRequestLogRepository $logs,
        private readonly TaxSettingsService $settings,
        private readonly MoyNalogService $moyNalog,
    ) {}

    public function queueSend(Invoice $invoice, ?int $initiatorUserId): Invoice
    {
        if (! $invoice->paid) {
            throw new RuntimeException('В налоговую можно отправлять только оплаченный инвойс.');
        }

        if (in_array($invoice->tax_status, ['queued', 'sending'], true)) {
            throw new RuntimeException('Инвойс уже находится в очереди на отправку.');
        }

        $updatedInvoice = $this->invoices->update($invoice, [
            'tax_status' => 'queued',
            'tax_queued_at' => now(),
            'tax_error_message' => null,
        ]);

        SendInvoiceToTaxJob::dispatch($updatedInvoice->id, $initiatorUserId);

        return $updatedInvoice;
    }

    public function handleSend(int $invoiceId, ?int $initiatorUserId = null): void
    {
        $invoice = $this->invoices->findWithRelations($invoiceId);

        if (! $invoice) {
            return;
        }

        $settings = $this->settings->getCurrent();

        if (! $settings instanceof TaxSetting) {
            $this->invoices->update($invoice, [
                'tax_status' => 'failed',
                'tax_last_error_at' => now(),
                'tax_error_message' => 'Не найдены TaxSettings.',
            ]);

            return;
        }

        $scope = 'invoice-'.$invoice->id;
        $log = $this->logs->create([
            'user_id' => $initiatorUserId,
            'invoice_id' => $invoice->id,
            'preset' => 'income',
            'action' => 'Create income receipt - individual',
            'method' => 'POST',
            'endpoint' => '/income',
            'status' => 'running',
            'request_payload' => $this->moyNalog->buildIncomePayload($invoice, $settings),
            'queued_at' => $invoice->tax_queued_at ?? now(),
            'started_at' => now(),
        ]);

        try {
            $this->invoices->update($invoice, [
                'tax_status' => 'sending',
                'tax_error_message' => null,
                'tax_last_error_at' => null,
                'tax_service_name' => $settings->service_name,
                'tax_estimated_commission' => round((float) $invoice->amount * 0.04, 2),
            ]);

            $this->moyNalog->authenticate($settings, $scope);
            $result = $this->moyNalog->createIncomeReceipt($invoice, $settings, $scope);
            $response = $result['response'];

            $this->logs->update($log, [
                'status' => $response->successful() ? 'completed' : 'failed',
                'response_status' => $response->status(),
                'response_headers' => $response->headers(),
                'response_body' => $response->body(),
                'response_json' => $result['json'] ?? null,
                'completed_at' => now(),
                'error_message' => $response->successful() ? null : $response->body(),
            ]);

            if (! $response->successful()) {
                $this->invoices->update($invoice, [
                    'tax_status' => 'failed',
                    'tax_last_error_at' => now(),
                    'tax_error_message' => $response->body(),
                    'tax_request_payload' => $result['payload'] ?? null,
                    'tax_response_payload' => $result['json'] ?? null,
                ]);

                return;
            }

            $this->invoices->update($invoice, [
                'tax_status' => 'sent',
                'tax_sent_at' => now(),
                'tax_service_name' => $settings->service_name,
                'tax_estimated_commission' => round((float) $invoice->amount * 0.04, 2),
                'tax_receipt_uuid' => $result['receipt_uuid'] ?: null,
                'tax_error_message' => null,
                'tax_request_payload' => $result['payload'] ?? null,
                'tax_response_payload' => $result['json'] ?? null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->logs->update($log, [
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $this->invoices->update($invoice, [
                'tax_status' => 'failed',
                'tax_last_error_at' => now(),
                'tax_error_message' => $exception->getMessage(),
            ]);
        } finally {
            $this->moyNalog->clearToken($scope);
        }
    }

    public function handleDebug(int $logId): void
    {
        $log = $this->logs->find($logId);

        if (! $log instanceof TaxRequestLog) {
            return;
        }

        $settings = $this->settings->getCurrent();

        if (! $settings instanceof TaxSetting) {
            $this->logs->update($log, [
                'status' => 'failed',
                'error_message' => 'Не найдены TaxSettings.',
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            return;
        }

        $scope = 'debug-'.$log->id;

        $this->logs->update($log, [
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = match ($log->preset) {
                'auth' => $this->moyNalog->authenticate($settings, $scope),
                'user' => $this->executeUserDebug($settings, $scope),
                'income' => $this->executeIncomeDebug($settings, $log, $scope),
                default => throw new RuntimeException('Неизвестный preset для налогового debug.'),
            };

            $response = $result['response'];

            $this->logs->update($log, [
                'status' => $response->successful() ? 'completed' : 'failed',
                'response_status' => $response->status(),
                'response_headers' => $response->headers(),
                'response_body' => $response->body(),
                'response_json' => $result['json'] ?? null,
                'completed_at' => now(),
                'error_message' => $response->successful() ? null : $response->body(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->logs->update($log, [
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        } finally {
            $this->moyNalog->clearToken($scope);
        }
    }

    /**
     * @return array{response: mixed, json: mixed}
     */
    private function executeUserDebug(TaxSetting $settings, string $scope): array
    {
        $this->moyNalog->authenticate($settings, $scope);

        return $this->moyNalog->getCurrentUser($scope);
    }

    /**
     * @return array{response: mixed, json: mixed, payload: array<string, mixed>, receipt_uuid: string}
     */
    private function executeIncomeDebug(TaxSetting $settings, TaxRequestLog $log, string $scope): array
    {
        $this->moyNalog->authenticate($settings, $scope);

        $invoiceId = (int) data_get($log->request_payload, 'invoice_id', $log->invoice_id);
        $invoice = $this->invoices->findWithRelations($invoiceId);

        if (! $invoice instanceof Invoice) {
            throw new RuntimeException('Для Income preset нужен invoice.');
        }

        return $this->moyNalog->createIncomeReceipt($invoice, $settings, $scope);
    }
}
