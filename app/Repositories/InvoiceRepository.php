<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Invoice
    {
        return Invoice::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Invoice $invoice, array $attributes): Invoice
    {
        $invoice->update($attributes);

        return $invoice->refresh();
    }

    public function delete(Invoice $invoice): void
    {
        $invoice->delete();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function latestWithRelations(): Collection
    {
        return Invoice::query()
            ->with(['user', 'transactions.type'])
            ->latest()
            ->get();
    }

    public function findWithRelations(int $id): ?Invoice
    {
        return Invoice::query()
            ->with(['transactions.type', 'user'])
            ->find($id);
    }
}
