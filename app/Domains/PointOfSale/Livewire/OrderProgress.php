<?php

namespace App\Domains\PointOfSale\Livewire;

use App\Domains\Kitchen\Models\KitchenTicket;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderProgress extends Component
{
    public bool $isOpen = false;
    public string $statusFilter = 'all'; // all, new, in_prep, ready, served
    public string $search = '';

    /**
     * Badge counts mapped cleanly to component property.
     *
     * @var array<string, int>
     */
    public array $counts = [
        'all' => 0,
        'new' => 0,
        'in_prep' => 0,
        'ready' => 0,
        'served' => 0,
    ];

    public int $readyCount = 0;

    #[On('open-order-progress')]
    public function open(): void
    {
        $this->isOpen = true;
        $branchId = Auth::user()?->branch_id ?? 1;
        $this->counts = $this->loadBadgeCounts($branchId);
        $this->readyCount = $this->counts['ready'] ?? 0;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = in_array($filter, ['all', 'new', 'in_prep', 'ready', 'served'], true)
            ? $filter
            : 'all';
    }

    #[On('kitchen-ticket-status-updated')]
    #[On('kitchen-ticket-claimed')]
    #[On('kitchen-ticket-item-status-updated')]
    #[On('payment-processed')]
    #[On('order-created')]
    #[On('order-voided')]
    public function refreshTickets(): void
    {
        $branchId = Auth::user()?->branch_id ?? 1;
        Cache::forget("branch_{$branchId}_order_progress_counts");

        if (! $this->isOpen) {
            $this->counts = $this->loadBadgeCounts($branchId);
            $this->readyCount = $this->counts['ready'] ?? 0;
            $this->skipRender();
        }
    }

    /**
     * Count the same active and today's served tickets included by the list query.
     * Cached with short TTL to minimize redundant aggregations.
     *
     * @param int|null $branchId
     * @return array<string, int>
     */
    public function loadBadgeCounts(?int $branchId = null): array
    {
        $branchId = $branchId ?? Auth::user()?->branch_id ?? 1;

        return Cache::remember("branch_{$branchId}_order_progress_counts", 10, function () use ($branchId) {
            $groupedCounts = $this->baseTicketQuery($branchId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $counts = [
                'new' => (int) ($groupedCounts[KitchenTicketStatus::NEW->value] ?? 0),
                'in_prep' => (int) ($groupedCounts[KitchenTicketStatus::IN_PREP->value] ?? 0),
                'ready' => (int) ($groupedCounts[KitchenTicketStatus::READY->value] ?? 0),
                'served' => (int) ($groupedCounts[KitchenTicketStatus::SERVED->value] ?? 0),
            ];

            // The All tab also includes today's served tickets.
            $counts['all'] = $counts['new'] + $counts['in_prep'] + $counts['ready'] + $counts['served'];

            return $counts;
        });
    }

    /**
     * Build ticket collection query with filters, sorting, and eager loading.
     */
    public function buildTicketQuery(?int $branchId = null): Builder
    {
        $branchId = $branchId ?? Auth::user()?->branch_id ?? 1;
        $query = $this->baseTicketQuery($branchId);

        if ($this->statusFilter === 'all') {
            // Show active tickets first, then recently served
            $query->whereIn('status', ['new', 'in_prep', 'ready', 'served']);
        } else {
            $query->where('status', $this->statusFilter);
        }

        // Apply search filter (order number, table number, ticket number, customer name)
        $search = trim($this->search);
        if (!empty($search)) {
            $cleanTable = trim((string) preg_replace('/^table\s+/i', '', $search));
            $query->where(function (Builder $q) use ($search, $cleanTable) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhereHas('order', function (Builder $orderQ) use ($search, $cleanTable) {
                        $orderQ->where('order_number', 'like', "%{$search}%")
                                ->orWhere('table_number', 'like', "%{$search}%")
                                ->orWhere('table_number', 'like', "%{$cleanTable}%")
                                ->orWhere('customer_name', 'like', "%{$search}%");
                });
            });
        }

        return $query
            ->with([
                'order.items',
                'items.orderItem',
                'cook:id,name,emp_id',
                'branch:id,name,code',
            ])
            ->orderByRaw("CASE 
                WHEN status = 'ready' THEN 0 
                WHEN status = 'in_prep' THEN 1 
                WHEN status = 'new' THEN 2 
                ELSE 3 
            END")
            ->orderByRaw("CASE WHEN priority = 'rush' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope tickets to the statuses the order-progress lists display.
     * Voided orders are excluded by both ticket and parent-order state.
     */
    private function baseTicketQuery(int $branchId): Builder
    {
        $startOfDay = today()->startOfDay();
        $startOfTomorrow = today()->addDay()->startOfDay();

        return KitchenTicket::query()
            ->where('branch_id', $branchId)
            ->where('status', '!=', KitchenTicketStatus::CANCELLED->value)
            ->where(function (Builder $query) use ($startOfDay, $startOfTomorrow) {
                $query->where(function (Builder $activeQuery) {
                    $activeQuery
                        ->whereIn('status', [
                            KitchenTicketStatus::NEW->value,
                            KitchenTicketStatus::IN_PREP->value,
                            KitchenTicketStatus::READY->value,
                        ])
                        ->whereNull('served_at');
                })->orWhere(function (Builder $servedQuery) use ($startOfDay, $startOfTomorrow) {
                    $servedQuery
                        ->where('status', KitchenTicketStatus::SERVED->value)
                        ->where('created_at', '>=', $startOfDay)
                        ->where('created_at', '<', $startOfTomorrow);
                });
            })
            ->whereHas('order', fn (Builder $query) => $query
                ->where('status', '!=', OrderStatus::CANCELLED->value)
                ->where('payment_status', '!=', PaymentStatus::REFUNDED->value)
            );
    }

    public function render()
    {
        $branchId = Auth::user()?->branch_id ?? 1;

        // Compute badge counts via single grouped aggregate SQL query
        $this->counts = $this->loadBadgeCounts($branchId);
        $this->readyCount = $this->counts['ready'] ?? 0;

        // ARCHITECTURAL GUARD: If slide-over is closed, bypass heavy ticket queries entirely
        if (! $this->isOpen) {
            return view('livewire.point-of-sale.order-progress', [
                'tickets' => collect(),
                'counts' => $this->counts,
                'readyCount' => $this->readyCount,
                'branch' => Auth::user()?->branch,
            ]);
        }

        // Only execute when actively opened by cashier
        $tickets = $this->buildTicketQuery($branchId)->take(50)->get();

        return view('livewire.point-of-sale.order-progress', [
            'tickets' => $tickets,
            'counts' => $this->counts,
            'readyCount' => $this->readyCount,
            'branch' => Auth::user()?->branch,
        ]);
    }
}
