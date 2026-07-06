<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InvoiceRepository
{
    private const STALE_PENDING_MINUTES = 30;

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
        return $this->visibleQuery()
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

    public function eligibleForTaxSend(): Builder
    {
        return $this->visibleQuery()
            ->where('paid', true)
            ->whereIn('tax_status', [
                Invoice::TAX_STATUS_NOT_SENT,
                Invoice::TAX_STATUS_FAILED,
            ])
            ->orderBy('id');
    }

    public function visibleQuery(): Builder
    {
        return Invoice::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('status', '!=', 'pending')
                    ->orWhere('created_at', '>=', now()->subMinutes(self::STALE_PENDING_MINUTES));
            });
    }
}
