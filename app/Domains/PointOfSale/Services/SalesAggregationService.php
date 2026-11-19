<?php

namespace App\Domains\PointOfSale\Services;

use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\Payment;
use App\Domains\PointOfSale\Services\Concerns\AggregatesPeriodicTrends;
use App\Domains\PointOfSale\Services\Concerns\AggregatesProductRankings;
use App\Domains\PointOfSale\Services\Concerns\AggregatesSalesSummary;
use App\Domains\PointOfSale\Services\Concerns\StreamsSalesCsv;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class SalesAggregationService
{
    use AggregatesSalesSummary;
    use AggregatesProductRankings;
    use AggregatesPeriodicTrends;
    use StreamsSalesCsv;

    /**
     * Runtime in-memory cache to deduplicate identical calls within the same request lifecycle.
     *
     * @var array<string, mixed>
     */
    protected array $runtimeCache = [];

    /**
     * Clear runtime in-memory cache.
     */
    public function clearRuntimeCache(): void
    {
        $this->runtimeCache = [];
    }

    /**
     * Resolve effective branch ID for caching and tenancy isolation.
     * For branch-scoped users without an assigned branch, returns -1 (fail-closed sentinel).
     */
    public function resolveBranchScope(?int $branchId): ?int
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user && method_exists($user, 'isBranchScoped') && $user->isBranchScoped()) {
                return method_exists($user, 'effectiveBranchId')
                    ? $user->effectiveBranchId()
                    : ($user->branch_id !== null ? (int) $user->branch_id : -1);
            }
        }

        return $branchId;
    }

    /**
     * Safely parse a date string into a Carbon instance, returning null if blank or malformed.
     */
    public function parseSafeDate(?string $dateString): ?Carbon
    {
        if (blank($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString, config('app.business_timezone'))->timezone(config('app.business_timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalize a date input string to Y-m-d if valid, or empty string if invalid/blank.
     */
    public function normalizeDateString(?string $dateString): string
    {
        return $this->parseSafeDate($dateString)?->toDateString() ?? '';
    }

    protected function applyBusinessDates(Builder|QueryBuilder $query, string $column, ?string $startDate, ?string $endDate): void
    {
        $start = $this->parseSafeDate($startDate);
        $end = $this->parseSafeDate($endDate);

        if ($start !== null) {
            $query->where($column, '>=', $start->startOfDay()->utc());
        }
        if ($end !== null) {
            $query->where($column, '<', $end->addDay()->startOfDay()->utc());
        }
    }

    /**
     * Build base query for filtered orders.
     */
    protected function buildFilteredOrdersQuery(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $effectiveBranchId = null,
        ?int $cashierId = null
    ): Builder {
        $query = Order::query()
            ->where(function ($q) {
                $q->where('orders.payment_status', PaymentStatus::PAID->value)
                    ->orWhere('orders.status', OrderStatus::COMPLETED->value);
            });

        $this->applyBusinessDates($query, 'orders.created_at', $startDate, $endDate);

        if ($effectiveBranchId !== null) {
            $query->where('orders.branch_id', $effectiveBranchId);
        }

        if ($cashierId) {
            $query->where('orders.cashier_id', $cashierId);
        }

        return $query;
    }

    /**
     * Build base query for filtered payments.
     */
    protected function buildFilteredPaymentsQuery(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $effectiveBranchId = null,
        ?int $cashierId = null
    ): Builder {
        $query = Payment::query()->where('status', 'successful');

        $this->applyBusinessDates($query, 'created_at', $startDate, $endDate);

        if ($effectiveBranchId !== null) {
            $query->where('branch_id', $effectiveBranchId);
        }

        if ($cashierId) {
            $query->where('cashier_id', $cashierId);
        }

        return $query;
    }

    /**
     * Helper to humanize payment method keys.
     */
    protected function formatPaymentMethod(string $method): string
    {
        return PaymentMethod::labelFor($method);
    }
}
