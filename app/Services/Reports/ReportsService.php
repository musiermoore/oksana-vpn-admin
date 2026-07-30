<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\DTOs\Reports\ReportsFilterData;
use App\Models\Invoice;
use App\Models\Server;
use App\Models\ServerPrice;
use App\Models\User;
use App\Models\UserSubscription;
use App\Repositories\InvoiceRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportsService
{
    private const TAX_RATE = 0.04;

    public function __construct(
        private readonly InvoiceRepository $invoices,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ReportsFilterData $filters): array
    {
        [$from, $to] = $this->resolveRange($filters);

        $paidInvoices = $this->invoices->visibleQuery()
            ->where('paid', true)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $invoicesCreatedInRange = $this->invoices->visibleQuery()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $revenue = round((float) $paidInvoices->sum('amount'), 2);
        $serverCosts = $this->buildServerCostBreakdown($from, $to);
        $totalServerCosts = round((float) $serverCosts->sum('total_cost'), 2);
        $estimatedTaxes = round($revenue * self::TAX_RATE, 2);
        $netProfit = round($revenue - $totalServerCosts - $estimatedTaxes, 2);

        $paidTaxInvoices = $paidInvoices->where('tax_status', '!=', '');
        $invoiceTaxStatusSegments = [
            ['label' => 'Отправлено', 'value' => $paidTaxInvoices->where('tax_status', Invoice::TAX_STATUS_SENT)->count(), 'color' => '#0f766e'],
            ['label' => 'В очереди', 'value' => $paidTaxInvoices->whereIn('tax_status', [Invoice::TAX_STATUS_QUEUED, Invoice::TAX_STATUS_SENDING])->count(), 'color' => '#f59e0b'],
            ['label' => 'Не отправлено', 'value' => $paidTaxInvoices->where('tax_status', Invoice::TAX_STATUS_NOT_SENT)->count(), 'color' => '#64748b'],
            ['label' => 'Ошибка', 'value' => $paidTaxInvoices->where('tax_status', Invoice::TAX_STATUS_FAILED)->count(), 'color' => '#dc2626'],
        ];

        $invoiceStateSegments = [
            ['label' => 'Оплаченные', 'value' => $invoicesCreatedInRange->where('paid', true)->count(), 'color' => '#0f766e'],
            ['label' => 'Неоплаченные', 'value' => $invoicesCreatedInRange->where('paid', false)->count(), 'color' => '#94a3b8'],
        ];

        $activeSubscribers = User::query()
            ->whereHas('activeSubscription', fn ($query) => $query
                ->whereDate('start_date', '<=', $to->toDateString())
                ->whereDate('end_date', '>=', $to->toDateString()))
            ->count();

        $newUsers = User::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->count();

        $subscriptionsStarted = UserSubscription::query()
            ->whereBetween('start_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return [
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'range_label' => sprintf('%s - %s', $from->format('d.m.Y'), $to->format('d.m.Y')),
            ],
            'summary' => [
                'revenue' => $revenue,
                'total_server_costs' => $totalServerCosts,
                'estimated_taxes' => $estimatedTaxes,
                'net_profit' => $netProfit,
                'margin_percent' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0.0,
                'paid_invoices_count' => $paidInvoices->count(),
                'active_subscribers' => $activeSubscribers,
                'new_users' => $newUsers,
                'subscriptions_started' => $subscriptionsStarted->count(),
                'subscriptions_revenue' => round((float) $subscriptionsStarted->sum('price'), 2),
                'average_invoice' => $paidInvoices->count() > 0
                    ? round($revenue / $paidInvoices->count(), 2)
                    : 0.0,
            ],
            'financial_segments' => [
                ['label' => 'Серверы', 'value' => $totalServerCosts, 'color' => '#1d4ed8'],
                ['label' => 'Налоги 4%', 'value' => $estimatedTaxes, 'color' => '#f97316'],
                ['label' => 'Чистая прибыль', 'value' => max(0, $netProfit), 'color' => '#16a34a'],
                ['label' => 'Убыток', 'value' => max(0, -$netProfit), 'color' => '#b91c1c'],
            ],
            'invoice_tax_status_segments' => $invoiceTaxStatusSegments,
            'invoice_state_segments' => $invoiceStateSegments,
            'server_costs' => $serverCosts->values()->all(),
            'top_servers' => $serverCosts->sortByDesc('total_cost')->take(6)->values()->all(),
            'monthly_trend' => $this->buildMonthlyTrend($to),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(ReportsFilterData $filters): array
    {
        $now = CarbonImmutable::now();
        $from = $filters->dateFrom
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters->dateFrom)->startOfDay()
            : $now->startOfMonth();
        $to = $filters->dateTo
            ? CarbonImmutable::createFromFormat('Y-m-d', $filters->dateTo)->endOfDay()
            : $now->endOfMonth();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return Collection<int, array{id:int,name:string,code:string,is_deleted:bool,total_cost:float,price_points:int}>
     */
    private function buildServerCostBreakdown(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Server::withTrashed()
            ->with('prices')
            ->get()
            ->map(function (Server $server) use ($from, $to): array {
                $totalCost = $this->calculateServerCostForRange($server->prices, $from, $to);

                return [
                    'id' => (int) $server->id,
                    'name' => (string) $server->name,
                    'code' => (string) $server->code,
                    'is_deleted' => $server->trashed(),
                    'total_cost' => round($totalCost, 2),
                    'price_points' => $server->prices->count(),
                ];
            })
            ->filter(fn (array $item): bool => $item['total_cost'] > 0)
            ->values();
    }

    /**
     * @param  Collection<int, ServerPrice>  $prices
     */
    private function calculateServerCostForRange(Collection $prices, CarbonImmutable $from, CarbonImmutable $to): float
    {
        if ($prices->isEmpty()) {
            return 0.0;
        }

        $sorted = $prices
            ->sortBy(fn (ServerPrice $price) => $price->effective_from?->format('Y-m-d'))
            ->values();

        $total = 0.0;
        $firstPrice = $sorted->first();

        if (! $firstPrice instanceof ServerPrice) {
            return 0.0;
        }

        if ($from->lt(CarbonImmutable::parse($firstPrice->effective_from?->format('Y-m-d')))) {
            $leadingEnd = CarbonImmutable::parse($firstPrice->effective_from?->format('Y-m-d'))->subDay();
            $overlapEnd = $leadingEnd->lt($to) ? $leadingEnd : $to;

            if ($overlapEnd->gte($from)) {
                $total += $this->calculateProratedMonthlyCost(
                    amount: (float) $firstPrice->price,
                    from: $from,
                    to: $overlapEnd,
                );
            }
        }

        foreach ($sorted as $index => $price) {
            $segmentStart = CarbonImmutable::parse($price->effective_from?->format('Y-m-d'));
            $nextPrice = $sorted->get($index + 1);
            $segmentEnd = $nextPrice instanceof ServerPrice
                ? CarbonImmutable::parse($nextPrice->effective_from?->format('Y-m-d'))->subDay()
                : $to;

            if ($segmentEnd->lt($from) || $segmentStart->gt($to)) {
                continue;
            }

            $overlapStart = $segmentStart->greaterThan($from) ? $segmentStart : $from;
            $overlapEnd = $segmentEnd->lessThan($to) ? $segmentEnd : $to;

            if ($overlapEnd->lt($overlapStart)) {
                continue;
            }

            $total += $this->calculateProratedMonthlyCost(
                amount: (float) $price->price,
                from: $overlapStart,
                to: $overlapEnd,
            );
        }

        return $total;
    }

    private function calculateProratedMonthlyCost(float $amount, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $total = 0.0;
        $cursor = $from->startOfDay();

        while ($cursor->lte($to)) {
            $segmentEnd = $cursor->endOfMonth()->lt($to) ? $cursor->endOfMonth() : $to;
            $daysInSegment = $cursor->diffInDays($segmentEnd) + 1;
            $total += ($amount / $cursor->daysInMonth) * $daysInSegment;
            $cursor = $segmentEnd->addDay()->startOfDay();
        }

        return $total;
    }

    /**
     * @return array<int, array{label:string,revenue:float,server_costs:float,estimated_taxes:float,net_profit:float}>
     */
    private function buildMonthlyTrend(CarbonImmutable $periodEnd): array
    {
        return collect(range(5, 0))
            ->map(function (int $offset) use ($periodEnd): array {
                $month = $periodEnd->startOfMonth()->subMonths($offset);
                $monthEnd = $month->endOfMonth();

                $revenue = round((float) $this->invoices->visibleQuery()
                    ->where('paid', true)
                    ->whereNotNull('paid_at')
                    ->whereBetween('paid_at', [$month->startOfDay(), $monthEnd->endOfDay()])
                    ->sum('amount'), 2);

                $serverCosts = round((float) $this->buildServerCostBreakdown($month, $monthEnd)->sum('total_cost'), 2);
                $estimatedTaxes = round($revenue * self::TAX_RATE, 2);

                return [
                    'label' => $month->translatedFormat('M Y'),
                    'revenue' => $revenue,
                    'server_costs' => $serverCosts,
                    'estimated_taxes' => $estimatedTaxes,
                    'net_profit' => round($revenue - $serverCosts - $estimatedTaxes, 2),
                ];
            })
            ->values()
            ->all();
    }
}
