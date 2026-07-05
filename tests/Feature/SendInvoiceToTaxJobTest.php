<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceToTaxJob;
use App\Models\Invoice;
use App\Models\TaxSetting;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Tax\InvoiceTaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SendInvoiceToTaxJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_invoice_to_tax_and_persists_statuses(): void
    {
        $user = User::query()->create([
            'name' => 'Client',
            'telegram' => '@client',
        ]);

        $type = TransactionType::query()->firstOrCreate([
            'slug' => TransactionType::SLUG_DEPOSIT,
        ], [
            'name' => 'Deposit',
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => 'pay-tax-1',
            'status' => 'succeeded',
            'paid' => true,
            'amount' => 1500,
            'currency' => 'RUB',
            'paid_at' => now(),
            'tax_status' => 'queued',
            'tax_queued_at' => now(),
        ]);

        Transaction::query()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'type_id' => $type->id,
            'amount' => 1500,
            'is_approved' => false,
            'description' => 'YooKassa',
        ]);

        TaxSetting::query()->create([
            'login' => '123456789012',
            'password' => 'secret',
            'service_name' => 'Настройка сетевой конфигурации',
        ]);

        Redis::shouldReceive('setex')->once();
        Redis::shouldReceive('get')->once()->andReturn('test-access-token');
        Redis::shouldReceive('del')->once();

        Http::fake([
            'https://lknpd.nalog.ru/api/v1/auth/lkfl' => Http::response([
                'accessToken' => 'test-access-token',
            ]),
            'https://lknpd.nalog.ru/api/v1/income' => Http::response([
                'approvedReceiptUuid' => 'receipt-123',
                'status' => 'approved',
            ], 200),
        ]);

        $job = new SendInvoiceToTaxJob($invoice->id, null);
        $job->handle(app(InvoiceTaxService::class));

        $invoice->refresh();

        $this->assertSame('sent', $invoice->tax_status);
        $this->assertSame('receipt-123', $invoice->tax_receipt_uuid);
        $this->assertSame(60.0, $invoice->tax_estimated_commission);
        $this->assertNotNull($invoice->tax_sent_at);
        $this->assertDatabaseHas('tax_request_logs', [
            'invoice_id' => $invoice->id,
            'status' => 'completed',
        ]);
    }
}
