<?php

namespace App\Domains\Kitchen\Livewire;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Actions\ClaimKitchenTicketAction;
use App\Domains\Kitchen\Actions\RecallKitchenTicketAction;
use App\Domains\Kitchen\Actions\ToggleKitchenItemStatusAction;
use App\Domains\Kitchen\Actions\ToggleKitchenTicketPriorityAction;
use App\Domains\Kitchen\Actions\UpdateKitchenTicketStatusAction;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Support\Enums\KitchenTicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class KitchenDisplay extends Component
{
    public function boot(): void
    {
        $this->resolveBranchId();
    }

    public string $diningFilter = 'all'; // all, dine_in, takeout, delivery

    public string $priorityFilter = 'all'; // all, rush

    public bool $showRecallDrawer = false;

    public bool $audioAlertEnabled = true;

    public int $lastKnownNewCount = 0;

    public ?string $lastTicketVersion = null;

    private function resolveBranchId(): int
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->can('kitchen.view')) {
            abort(403, 'Unauthorized.');
        }

        $branchId = $user->effectiveBranchId();
        if ($branchId === null || $branchId < 0) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        return $branchId;
    }

    public function mount(): void
    {
        $branchId = $this->resolveBranchId();
        $this->lastKnownNewCount = KitchenTicket::forBranch($branchId)
            ->where('status', KitchenTicketStatus::NEW->value)
            ->count();
        $this->lastTicketVersion = $this->getTicketSignatureVersion($branchId);
    }

    public function getTicketSignatureVersion(int $branchId): string
    {
        $user = Auth::user();
        if ($user instanceof User) {
            abort_unless($user->can('kitchen.view'), 403);
            if (! $branchId && $user->isBranchScoped()) {
                $branchId = $this->resolveBranchId();
            }
        }
        $todayStart = now(config('app.business_timezone'))->startOfDay()->utc();

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
        $branchId = $this->resolveBranchId();
        /** @var User $user */
        $user = Auth::user();

        $ticket = KitchenTicket::forBranch($branchId)->findOrFail($ticketId);
        $action->execute($ticket, $user);
    }

    public function markReady(int $ticketId, UpdateKitchenTicketStatusAction $action): void
    {
        $branchId = $this->resolveBranchId();
        $ticket = KitchenTicket::forBranch($branchId)->findOrFail($ticketId);
        $action->execute($ticket, KitchenTicketStatus::READY->value);
    }

    public function markServed(int $ticketId, UpdateKitchenTicketStatusAction $action): void
    {
        $branchId = $this->resolveBranchId();
        $ticket = KitchenTicket::forBranch($branchId)->findOrFail($ticketId);
        $action->execute($ticket, KitchenTicketStatus::SERVED->value);
    }

    public function toggleItem(int $itemId, ToggleKitchenItemStatusAction $action): void
    {
        $branchId = $this->resolveBranchId();
        $item = KitchenTicketItem::whereHas('ticket', function (Builder $q) use ($branchId): void {
            /** @var Builder<KitchenTicket> $q */
            $q->forBranch($branchId);
        })->findOrFail($itemId);
        $action->execute($item);
    }

    public function togglePriority(int $ticketId, ToggleKitchenTicketPriorityAction $action): void
    {
        $branchId = $this->resolveBranchId();
        $ticket = KitchenTicket::forBranch($branchId)->findOrFail($ticketId);
        $action->execute($ticket);
    }

    public function recallTicket(int $ticketId, RecallKitchenTicketAction $action): void
    {
        $branchId = $this->resolveBranchId();
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
        $branchId = $this->resolveBranchId();
        $version = $this->getTicketSignatureVersion($branchId);

        if ($this->lastTicketVersion !== null && $version === $this->lastTicketVersion) {
            $this->skipRender();

            return;
        }

        $this->lastTicketVersion = $version;

        $currentNewCount = KitchenTicket::forBranch($branchId)
            ->where('status', KitchenTicketStatus::NEW->value)
            ->count();

        if ($this->audioAlertEnabled && $currentNewCount > $this->lastKnownNewCount) {
            $this->dispatch('play-kitchen-chime');
        }

        $this->lastKnownNewCount = $currentNewCount;
    }

    public function render()
    {
        $branchId = $this->resolveBranchId();

        $baseQuery = KitchenTicket::query()
            ->forBranch($branchId)
            ->where('status', '!=', KitchenTicketStatus::CANCELLED->value)
            ->whereOrderNotVoided()
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

        $newTickets = $allTickets->where('status', KitchenTicketStatus::NEW->value)->values();
        $inPrepTickets = $allTickets->where('status', KitchenTicketStatus::IN_PREP->value)->values();
        $readyTickets = $allTickets->where('status', KitchenTicketStatus::READY->value)->values();

        $todayStart = now(config('app.business_timezone'))->startOfDay()->utc();
        $tomorrowStart = $todayStart->copy()->addDay();

        $recentServedTickets = KitchenTicket::query()
            ->forBranch($branchId)
            ->where('status', '!=', KitchenTicketStatus::CANCELLED->value)
            ->whereNotNull('served_at')
            ->where('served_at', '>=', $todayStart)
            ->where('served_at', '<', $tomorrowStart)
            ->whereOrderNotVoided()
            ->with(['items.orderItem.meal', 'order', 'cook'])
            ->orderBy('served_at', 'desc')
            ->limit(50)
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
