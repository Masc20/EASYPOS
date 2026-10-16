<?php

namespace App\Domains\Kitchen\Livewire;

use App\Domains\Kitchen\Actions\ClaimKitchenTicketAction;
use App\Domains\Kitchen\Actions\RecallKitchenTicketAction;
use App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction;
use App\Domains\Kitchen\Actions\ToggleKitchenTicketPriorityAction;
use App\Domains\Kitchen\Actions\UpdateKitchenTicketStatusAction;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class KitchenDisplay extends Component
{
    public string $diningFilter = 'all'; // all, dine_in, takeout, delivery
    public string $priorityFilter = 'all'; // all, rush
    public bool $showRecallDrawer = false;
    public bool $audioAlertEnabled = true;
    public int $lastKnownNewCount = 0;

    public function mount(): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $this->lastKnownNewCount = KitchenTicket::forBranch($branchId)
            ->where('status', 'new')
            ->count();
    }

    public function claimTicket(int $ticketId, ClaimKitchenTicketAction $action): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $ticket = KitchenTicket::findOrFail($ticketId);
        $action->execute($ticket, $user);
    }

    public function markReady(int $ticketId, UpdateKitchenTicketStatusAction $action): void
    {
        $ticket = KitchenTicket::findOrFail($ticketId);
        $action->execute($ticket, 'ready');
    }

    public function markServed(int $ticketId, UpdateKitchenTicketStatusAction $action): void
    {
        $ticket = KitchenTicket::findOrFail($ticketId);
        $action->execute($ticket, 'served');
    }

    public function toggleItem(int $itemId, ToggleKitchenItemStatusAction $action): void
    {
        $item = KitchenTicketItem::findOrFail($itemId);
        $action->execute($item);
    }

    public function togglePriority(int $ticketId, ToggleKitchenTicketPriorityAction $action): void
    {
        $ticket = KitchenTicket::findOrFail($ticketId);
        $action->execute($ticket);
    }

    public function recallTicket(int $ticketId, RecallKitchenTicketAction $action): void
    {
        $ticket = KitchenTicket::findOrFail($ticketId);
        $action->execute($ticket);
    }

    public function toggleRecallDrawer(): void
    {
        $this->showRecallDrawer = !$this->showRecallDrawer;
    }

    public function toggleAudioAlert(): void
    {
        $this->audioAlertEnabled = !$this->audioAlertEnabled;
    }

    public function pollUpdates(): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $currentNewCount = KitchenTicket::forBranch($branchId)
            ->where('status', 'new')
            ->count();

        if ($this->audioAlertEnabled && $currentNewCount > $this->lastKnownNewCount) {
            $this->dispatch('play-kitchen-chime');
        }

        $this->lastKnownNewCount = $currentNewCount;
    }

    public function render()
    {
        $branchId = Auth::user()->branch_id ?? 1;

        $baseQuery = KitchenTicket::query()
            ->forBranch($branchId)
            ->with(['items.orderItem.meal', 'order', 'cook'])
            ->rushFirst();

        // Apply Dining Filter
        if ($this->diningFilter !== 'all') {
            $baseQuery->whereHas('order', function ($q) {
                $q->where('order_type', $this->diningFilter);
            });
        }

        // Apply Priority Filter
        if ($this->priorityFilter === 'rush') {
            $baseQuery->where('priority', 'rush');
        }

        $allTickets = (clone $baseQuery)->active()->get();

        $newTickets = $allTickets->where('status', 'new')->values();
        $inPrepTickets = $allTickets->where('status', 'in_prep')->values();
        $readyTickets = $allTickets->where('status', 'ready')->values();

        $recentServedTickets = KitchenTicket::query()
            ->forBranch($branchId)
            ->whereNotNull('served_at')
            ->with(['items.orderItem.meal', 'order', 'cook'])
            ->orderBy('served_at', 'desc')
            ->limit(10)
            ->get();

        return view('livewire.kitchen.kitchen-display', [
            'newTickets' => $newTickets,
            'inPrepTickets' => $inPrepTickets,
            'readyTickets' => $readyTickets,
            'recentServedTickets' => $recentServedTickets,
            'counts' => [
                'new' => $newTickets->count(),
                'in_prep' => $inPrepTickets->count(),
                'ready' => $readyTickets->count(),
                'total_active' => $allTickets->count(),
            ],
        ]);
    }
}
