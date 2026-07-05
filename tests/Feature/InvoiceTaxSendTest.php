<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceToTaxJob;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceTaxSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_can_be_queued_for_tax_send(): void
    {
        Queue::fake();

        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $user = User::query()->create([
            'name' => 'Client',
            'telegram' => '@client',
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => 'pay-1',
            'status' => 'succeeded',
            'paid' => true,
            'amount' => 1200,
            'currency' => 'RUB',
        ]);

        $this->actingAs($admin)
            ->post(route('invoices.send', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();

        $this->assertSame('queued', $invoice->tax_status);
        $this->assertNotNull($invoice->tax_queued_at);

        Queue::assertPushed(SendInvoiceToTaxJob::class, fn (SendInvoiceToTaxJob $job) => $job->invoiceId === $invoice->id);
    }

    public function test_unpaid_invoice_cannot_be_queued_for_tax_send(): void
    {
        Queue::fake();

        $admin = User::query()->create([
            'name' => 'Admin',
            'telegram' => '@admin',
        ]);

        $user = User::query()->create([
            'name' => 'Client',
            'telegram' => '@client',
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => 'pay-2',
            'status' => 'pending',
            'paid' => false,
            'amount' => 800,
            'currency' => 'RUB',
        ]);

        $this->actingAs($admin)
            ->post(route('invoices.send', $invoice))
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }
}
