<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns the dashboard filters (period, sales rep, customer) into date ranges and filtered queries,
 * so every widget counts the same way.
 */
trait UsesDashboardFilters
{
    use InteractsWithPageFilters;

    // A lazy widget that has not scrolled into view yet crashes when the filters change, so load them all up front.
    public static function isLazy(): bool
    {
        return false;
    }

    /**
     * SQL for the share of list price the customer actually paid on an order line's order (order total / items total),
     * so revenue and profit follow the order discount. Use where `order_items` is in the query.
     */
    public const PAID_RATIO_SQL = '(SELECT COALESCE(o.total_amount / NULLIF(SUM(x.unit_price * x.quantity), 0), 1) FROM order_items x JOIN orders o ON o.id = x.order_id WHERE x.order_id = order_items.order_id GROUP BY o.total_amount)';

    public const PERIODS = [
        'today' => 'Today',
        'last_7_days' => 'Last 7 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'this_year' => 'This year',
        'all' => 'All time',
        'custom' => 'Custom range',
    ];

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    protected function range(): array
    {
        $now = CarbonImmutable::now();

        return match ($this->filters['period'] ?? 'this_month') {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->startOfQuarter(), $now->endOfDay()],
            'this_year' => [$now->startOfYear(), $now->endOfDay()],
            'all' => [CarbonImmutable::create(2000), $now->endOfDay()],
            'custom' => [
                CarbonImmutable::parse($this->filters['from'] ?? $now->startOfMonth())->startOfDay(),
                CarbonImmutable::parse($this->filters['until'] ?? $now)->endOfDay(),
            ],
            default => [$now->startOfMonth(), $now->endOfDay()],
        };
    }

    /** The same length of time just before the selected range, for "vs previous period". */
    protected function previousRange(): ?array
    {
        if (($this->filters['period'] ?? null) === 'all') {
            return null;
        }

        [$from, $until] = $this->range();
        $days = $from->diffInDays($until) + 1;

        return [$from->subDays($days), $until->subDays($days)];
    }

    /** Sales reps only ever see their own numbers. */
    protected function repId(): ?int
    {
        $user = auth()->user();
        if ($user?->onlySeesOwnSales()) {
            return $user->id;
        }

        return filled($this->filters['sales_rep_id'] ?? null) ? (int) $this->filters['sales_rep_id'] : null;
    }

    protected function customerId(): ?int
    {
        return filled($this->filters['customer_id'] ?? null) ? (int) $this->filters['customer_id'] : null;
    }

    /** Non-cancelled orders for the rep/customer filter; pass a range to limit by order date. */
    protected function orders(?array $range = null): Builder
    {
        return Order::query()
            ->where('status', '!=', 'cancelled')
            ->when($range, fn ($q) => $q->whereBetween('date', [$range[0]->toDateString(), $range[1]->toDateString()]))
            ->when($this->repId(), fn ($q, $id) => $q->where('sales_rep_id', $id))
            ->when($this->customerId(), fn ($q, $id) => $q->where('customer_id', $id));
    }

    /** Payments that count (not bounced), by payment date. */
    protected function payments(?array $range = null): Builder
    {
        return Payment::query()
            ->where('status', '!=', 'bounced')
            ->when($range, fn ($q) => $q->whereBetween('transaction_date', [$range[0]->toDateString(), $range[1]->toDateString()]))
            ->when($this->repId(), fn ($q, $id) => $q->where('sales_rep_id', $id))
            ->when($this->customerId(), fn ($q, $id) => $q->whereHas('invoice.order', fn ($o) => $o->where('customer_id', $id)));
    }

    /** Returns, by return date, for orders matching the rep/customer filter. */
    protected function returns(?array $range = null): Builder
    {
        return OrderReturn::query()
            ->when($range, fn ($q) => $q->whereBetween('return_date', [$range[0]->toDateString(), $range[1]->toDateString()]))
            ->whereIn('order_id', $this->orders()->select('id'));
    }

    protected function netSales(array $range): float
    {
        return (float) $this->orders($range)->sum('total_amount') - (float) $this->returns($range)->sum('amount');
    }

    /** Item profit (price actually paid after the order discount, minus cost) on orders in the range, less units returned. */
    protected function grossProfit(array $range): float
    {
        return (float) OrderItem::query()
            ->whereIn('order_id', $this->orders($range)->select('id'))
            ->selectRaw('SUM((unit_price * ' . self::PAID_RATIO_SQL . ' - cost_price) * (quantity - COALESCE((SELECT SUM(r.quantity) FROM order_return_items r WHERE r.order_item_id = order_items.id), 0))) as profit')
            ->value('profit');
    }

    /** Expenses are company-wide, so they only follow the date filter. */
    protected function expenses(array $range): float
    {
        return (float) Expense::whereBetween('date', [$range[0]->toDateString(), $range[1]->toDateString()])->sum('amount');
    }

    protected function money(float $value): string
    {
        return 'LKR ' . number_format($value, 2);
    }

    /** "12% vs previous period" with direction, or null when there is nothing to compare. */
    protected function change(float $current, ?float $previous): ?array
    {
        if ($previous === null || abs($previous) < 0.01) {
            return null;
        }

        $pct = ($current - $previous) / abs($previous) * 100;

        return [
            'text' => abs(round($pct)) . '% ' . ($pct >= 0 ? 'up' : 'down') . ' vs previous period',
            'up' => $pct >= 0,
        ];
    }
}
