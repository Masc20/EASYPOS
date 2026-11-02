<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateKitchenTicketStatusAction
{
    /**
     * Transition ticket to a new status and sync order state.
     */
    public function execute(KitchenTicket $ticket, string $newStatus): KitchenTicket
    {
        if (auth()->check() && ! auth()->user()->can('kitchen.prepare')) {
            abort(403, 'Unauthorized. Only kitchen staff can update kitchen ticket status.');
        }

        if (!in_array($newStatus, KitchenTicketStatus::values(), true)) {
            throw new InvalidArgumentException("Invalid kitchen ticket status: {$newStatus}");
        }

        return DB::transaction(function () use ($ticket, $newStatus) {
            $previousStatus = $ticket->status;
            $updates = ['status' => $newStatus];

            if ($newStatus === KitchenTicketStatus::IN_PREP->value && !$ticket->started_at) {
                $updates['started_at'] = now();
            } elseif ($newStatus === KitchenTicketStatus::READY->value) {
                $updates['ready_at'] = now();
                // When ticket is marked ready, mark all unfinished items as done
                $ticket->items()->where('status', '!=', 'done')->update([
                    'status' => 'done',
                    'completed_at' => now(),
                ]);
            } elseif ($newStatus === KitchenTicketStatus::SERVED->value) {
                $updates['served_at'] = now();
                // When served, sync associated order to completed
                if ($ticket->order) {
                    $ticket->order->update([
                        'status' => OrderStatus::COMPLETED->value,
                        'completed_at' => now(),
                    ]);
                }
            } elseif ($newStatus === KitchenTicketStatus::CANCELLED->value) {
                if ($ticket->order) {
                    $ticket->order->update([
                        'status' => OrderStatus::CANCELLED->value,
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
