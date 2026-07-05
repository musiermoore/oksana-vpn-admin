<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TaxRequestLog;
use Illuminate\Database\Eloquent\Collection;

class TaxRequestLogRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TaxRequestLog
    {
        return TaxRequestLog::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TaxRequestLog $log, array $attributes): TaxRequestLog
    {
        $log->update($attributes);

        return $log->refresh();
    }

    public function find(int $id): ?TaxRequestLog
    {
        return TaxRequestLog::query()->find($id);
    }

    /**
     * @return Collection<int, TaxRequestLog>
     */
    public function latestWithInvoice(int $limit = 20): Collection
    {
        return TaxRequestLog::query()
            ->with('invoice')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, TaxRequestLog>
     */
    public function latestForInvoice(int $invoiceId, int $limit = 10): Collection
    {
        return TaxRequestLog::query()
            ->with('invoice')
            ->where('invoice_id', $invoiceId)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
