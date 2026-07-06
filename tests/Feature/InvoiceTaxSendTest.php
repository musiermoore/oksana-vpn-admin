<?php

namespace Tests\Feature;

use App\Console\Commands\SendPaidInvoicesToTaxCommand;
use App\Jobs\SendInvoiceToTaxJob;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
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

    public function test_paid_invoices_can_be_bulk_queued_for_tax_send(): void
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

        $eligibleInvoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => 'pay-bulk-1',
            'status' => 'succeeded',
            'paid' => true,
            'amount' => 1000,
            'currency' => 'RUB',
            'tax_status' => Invoice::TAX_STATUS_NOT_SENT,
        ]);

        $failedInvoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => 'pay-bulk-2',
            'status' => 'succeeded',
            'paid' => true,
            'amount' => 1100,
            'currency' => 'RUB',
            'tax_status' => Invoice::TAX_STATUS_FAILED,
        ]);

        $alreadySentInvoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => 'pay-bulk-3',
            'status' => 'succeeded',
            'paid' => true,
            'amount' => 1200,
            'currency' => 'RUB',
            'tax_status' => Invoice::TAX_STATUS_SENT,
        ]);

        $this->actingAs($admin)
            ->post(route('invoices.send-paid'))
            ->assertRedirect(route('invoices.index'));

        $eligibleInvoice->refresh();
        $failedInvoice->refresh();
        $alreadySentInvoice->refresh();

        $this->assertSame(Invoice::TAX_STATUS_QUEUED, $eligibleInvoice->tax_status);
        $this->assertSame(Invoice::TAX_STATUS_QUEUED, $failedInvoice->tax_status);
        $this->assertSame(Invoice::TAX_STATUS_SENT, $alreadySentInvoice->tax_status);

        Queue::assertPushed(SendInvoiceToTaxJob::class, 2);
    }

    public function test_tax_status_can_be_updated_manually(): void
    {
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
            'provider_payment_id' => 'pay-manual-1',
            'status' => 'succeeded',
            'paid' => true,
            'amount' => 1200,
            'currency' => 'RUB',
            'tax_status' => Invoice::TAX_STATUS_FAILED,
        ]);

        $this->actingAs($admin)
            ->patch(route('invoices.update-tax-status', $invoice), [
                'tax_status' => Invoice::TAX_STATUS_SENT,
            ])
            ->assertRedirect(route('invoices.edit', $invoice));

        $invoice->refresh();

        $this->assertSame(Invoice::TAX_STATUS_SENT, $invoice->tax_status);
        $this->assertNotNull($invoice->tax_sent_at);
        $this->assertNull($invoice->tax_error_message);
    }

    public function test_send_paid_invoices_command_is_scheduled_daily_at_six_utc(): void
    {
        $events = collect(Schedule::events());

        $event = $events->first(function ($scheduledEvent) {
            return str_contains((string) $scheduledEvent->command, 'invoices:send-paid-to-tax');
        });

        $this->assertNotNull($event);
        $this->assertSame('0 6 * * *', $event->expression);
        $this->assertSame('UTC', $event->timezone);
    }

    public function test_send_paid_invoices_command_queues_eligible_invoices(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Client',
            'telegram' => '@client',
        ]);

        $invoice = Invoice::query()->create([
            'user_id' => $user->id,
            'provider' => 'yookassa',
            'provider_payment_id' => 'pay-command-1',
            'status' => 'succeeded',
            'paid' => true,
            'amount' => 1300,
            'currency' => 'RUB',
            'tax_status' => Invoice::TAX_STATUS_NOT_SENT,
        ]);

        Artisan::call(SendPaidInvoicesToTaxCommand::class);

        $invoice->refresh();

        $this->assertSame(Invoice::TAX_STATUS_QUEUED, $invoice->tax_status);
        Queue::assertPushed(SendInvoiceToTaxJob::class, 1);
    }
}
