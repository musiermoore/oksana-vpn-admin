<?php

namespace App\Repositories;

use App\Models\Invoice;

class InvoiceRepository
{
    public function create(array $attributes): Invoice
    {
        return Invoice::create($attributes);
    }

    public function update(Invoice $invoice, array $attributes): Invoice
    {
        $invoice->update($attributes);

        return $invoice->refresh();
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }
}
