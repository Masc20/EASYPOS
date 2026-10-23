<?php

namespace App\Domains\PointOfSale\Livewire;

use App\Domains\Kitchen\Models\KitchenTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderProgress extends Component
{
    public bool $isOpen = false;
    public string $statusFilter = 'all'; // all, new, in_prep, ready, served
    public string $search = '';

    #[On('open-order-progress')]
    public function open(): void
    {
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $filter;
    }

    #[On('kitchen-ticket-status-updated')]
    #[On('kitchen-ticket-claimed')]
    #[On('kitchen-ticket-item-status-updated')]
    #[On('payment-processed')]
    #[On('order-created')]
    public function refreshTickets(): void
    {
        // Re-render component on real-time kitchen lifecycle events
    }

    public function render()
    {
        $branchId = Auth::user()?->branch_id ?? 1;

        // Base scoped query for cashier's branch
        $baseQuery = KitchenTicket::query()
            ->where('branch_id', $branchId)
            ->where(function (Builder $q) {
                // Active tickets or tickets from today
                $q->whereNull('served_at')
                  ->where('status', '!=', 'cancelled')
                  ->orWhereDate('created_at', today());
            });

        // Compute counts for tab badges
        $countsQuery = clone $baseQuery;
        $allTicketsForCounts = $countsQuery->select(['id', 'status', 'created_at'])->get();

        $counts = [
            'all' => $allTicketsForCounts->whereIn('status', ['new', 'in_prep', 'ready'])->count(),
            'new' => $allTicketsForCounts->where('status', 'new')->count(),
            'in_prep' => $allTicketsForCounts->where('status', 'in_prep')->count(),
            'ready' => $allTicketsForCounts->where('status', 'ready')->count(),
            'served' => $allTicketsForCounts->where('status', 'served')->count(),
        ];

        // Filter by selected tab
        $query = clone $baseQuery;

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

        $tickets = $query
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
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return view('livewire.point-of-sale.order-progress', [
            'tickets' => $tickets,
            'counts' => $counts,
            'readyCount' => $counts['ready'],
            'branch' => Auth::user()?->branch,
        ]);
    }
}
