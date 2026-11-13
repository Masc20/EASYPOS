<?php

namespace App\Domains\Kitchen\Livewire;

use App\Domains\Kitchen\Actions\ClaimKitchenTicketAction;
use App\Domains\Kitchen\Actions\RecallKitchenTicketAction;
use App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction;
use App\Domains\Kitchen\Actions\ToggleKitchenTicketPriorityAction;
use App\Domains\Kitchen\Actions\UpdateKitchenTicketStatusAction;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Support\Enums\KitchenTicketStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class KitchenDisplay extends Component
{
    public string $diningFilter = 'all'; // all, dine_in, takeout, delivery
    public string $priorityFilter = 'all'; // all, rush
    public bool $showRecallDrawer = false;
    public bool $audioAlertEnabled = true;
    public int $lastKnownNewCount = 0;
    public ?string $lastTicketVersion = null;

    public function mount(): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $this->lastKnownNewCount = KitchenTicket::forBranch($branchId)
            ->where('status', 'new')
            ->count();
        $this->lastTicketVersion = $this->getTicketSignatureVersion($branchId);
    }

    public function getTicketSignatureVersion(int $branchId): string
    {
        $todayStart = now()->startOfDay();

        $signature = KitchenTicket::forBranch($branchId)
            ->where('status', '!=', KitchenTicketStatus::CANCELLED->value)
            ->where(function ($q) use ($todayStart) {
                $q->whereNull('served_at')
                    ->orWhere('served_at', '>=', $todayStart);
            })
            ->selectRaw('COUNT(*) as total, MAX(updated_at) as last_updated, MAX(id) as max_id')
            ->first();

        return md5(($signature->total ?? 0).':'.($signature->last_updated ?? '').':'.($signature->max_id ?? 0));
    }

    public function claimTicket(int $ticketId, ClaimKitchenTicketAction $action): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $ticket = KitchenTicket::forBranch($user->branch_id ?? 1)->findOrFail($ticketId);
        $action->execute($ticket, $user);
    }

    public function markReady(int $ticketId, UpdateKitchenTicketStatusAction $action): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $ticket = KitchenTicket::forBranch($branchId)->findOrFail($ticketId);
        $action->execute($ticket, 'ready');
    }

    public function markServed(int $ticketId, UpdateKitchenTicketStatusAction $action): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $ticket = KitchenTicket::forBranch($branchId)->findOrFail($ticketId);
        $action->execute($ticket, 'served');
    }

    public function toggleItem(int $itemId, ToggleKitchenItemStatusAction $action): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $item = KitchenTicketItem::whereHas('ticket', fn ($q) => $q->forBranch($branchId))->findOrFail($itemId);
        $action->execute($item);
    }

    public function togglePriority(int $ticketId, ToggleKitchenTicketPriorityAction $action): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $ticket = KitchenTicket::forBranch($branchId)->findOrFail($ticketId);
        $action->execute($ticket);
    }

    public function recallTicket(int $ticketId, RecallKitchenTicketAction $action): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $ticket = KitchenTicket::forBranch($branchId)->findOrFail($ticketId);
        $action->execute($ticket);
    }

    public function toggleRecallDrawer(): void
    {
        $this->showRecallDrawer = ! $this->showRecallDrawer;
    }

    public function toggleAudioAlert(): void
    {
        $this->audioAlertEnabled = ! $this->audioAlertEnabled;
    }

    public function pollUpdates(): void
    {
        $branchId = Auth::user()->branch_id ?? 1;
        $version = $this->getTicketSignatureVersion($branchId);

        if ($this->lastTicketVersion !== null && $version === $this->lastTicketVersion) {
            $this->skipRender();

            return;
        }

        $this->lastTicketVersion = $version;

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
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('order_id')->orWhereHas('order', function ($oq) {
                    $oq->where('status', '!=', 'cancelled')
                        ->where('payment_status', '!=', 'refunded');
                });
            })
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

        $totalActiveCount = (clone $baseQuery)->active()->count();
        $allTickets = (clone $baseQuery)->active()->limit(75)->get();
        $overflowCount = max(0, $totalActiveCount - 75);

        $newTickets = $allTickets->where('status', 'new')->values();
        $inPrepTickets = $allTickets->where('status', 'in_prep')->values();
        $readyTickets = $allTickets->where('status', 'ready')->values();

        $recentServedTickets = KitchenTicket::query()
            ->forBranch($branchId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('served_at')
            ->where(function ($q) {
                $q->whereNull('order_id')->orWhereHas('order', function ($oq) {
                    $oq->where('status', '!=', 'cancelled')
                        ->where('payment_status', '!=', 'refunded');
                });
            })
            ->with(['items.orderItem.meal', 'order', 'cook'])
            ->orderBy('served_at', 'desc')
            ->limit(10)
            ->get();

        return view('livewire.kitchen.kitchen-display', [
            'newTickets' => $newTickets,
            'inPrepTickets' => $inPrepTickets,
            'readyTickets' => $readyTickets,
            'recentServedTickets' => $recentServedTickets,
            'totalActiveCount' => $totalActiveCount,
            'overflowCount' => $overflowCount,
            'counts' => [
                'new' => $newTickets->count(),
                'in_prep' => $inPrepTickets->count(),
                'ready' => $readyTickets->count(),
                'total_active' => $totalActiveCount,
            ],
        ]);
    }
}
