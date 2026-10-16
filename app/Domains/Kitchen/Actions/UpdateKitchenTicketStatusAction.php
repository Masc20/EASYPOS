<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicket;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateKitchenTicketStatusAction
{
    /**
     * Transition ticket to a new status and sync order state.
     */
    public function execute(KitchenTicket $ticket, string $newStatus): KitchenTicket
    {
        $allowed = ['new', 'in_prep', 'ready', 'served', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException("Invalid kitchen ticket status: {$newStatus}");
        }

        return DB::transaction(function () use ($ticket, $newStatus) {
            $previousStatus = $ticket->status;
            $updates = ['status' => $newStatus];

            if ($newStatus === 'in_prep' && !$ticket->started_at) {
                $updates['started_at'] = now();
            } elseif ($newStatus === 'ready') {
                $updates['ready_at'] = now();
                // When ticket is marked ready, mark all unfinished items as done
                $ticket->items()->where('status', '!=', 'done')->update([
                    'status' => 'done',
                    'completed_at' => now(),
                ]);
            } elseif ($newStatus === 'served') {
                $updates['served_at'] = now();
                // When served, sync associated order to completed
                if ($ticket->order) {
                    $ticket->order->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                }
            } elseif ($newStatus === 'cancelled') {
                if ($ticket->order) {
                    $ticket->order->update([
                        'status' => 'cancelled',
                    ]);
                }
            }

            $ticket->update($updates);
            $ticket->load(['items.orderItem.meal', 'order', 'cook']);

            event(new KitchenTicketStatusUpdated($ticket, $previousStatus, $newStatus));

            return $ticket;
        });
    }
}
