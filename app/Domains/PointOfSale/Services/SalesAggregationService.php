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
     */
    public function resolveBranchScope(?int $branchId): ?int
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user && method_exists($user, 'isBranchScoped') && $user->isBranchScoped()) {
                return (int) $user->branch_id;
            }
        }

        return $branchId;
    }

    /**
     * Build base query for filtered orders.
     */
    protected function buildFilteredOrdersQuery(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $branchId = null,
        ?int $cashierId = null
    ): Builder {
        $query = Order::query()
            ->where(function ($q) {
                $q->where('orders.payment_status', PaymentStatus::PAID->value)
                    ->orWhere('orders.status', OrderStatus::COMPLETED->value);
            });

        if ($startDate) {
            $query->where('orders.created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('orders.created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($branchId) {
            $query->where('orders.branch_id', $branchId);
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
        ?int $branchId = null,
        ?int $cashierId = null
    ): Builder {
        $query = Payment::query()->where('status', 'successful');

        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
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
