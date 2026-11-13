<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Events\KitchenTicketItemStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use Illuminate\Support\Facades\DB;

class ToggleKitchenItemStatusAction
{
    /**
     * Toggle item between cooking/pending and done.
     */
    public function execute(KitchenTicketItem $item, ?string $forceStatus = null): KitchenTicketItem
    {
        if (auth()->check() && ! auth()->user()->can('kitchen.prepare')) {
            abort(403, 'Unauthorized. Only kitchen staff can modify kitchen ticket items.');
        }

        return DB::transaction(function () use ($item, $forceStatus) {
            if ($forceStatus) {
                $newStatus = $forceStatus;
            } else {
                $newStatus = ($item->status === 'done') ? 'cooking' : 'done';
            }

            $updates = ['status' => $newStatus];
            if ($newStatus === 'done') {
                $updates['completed_at'] = now();
            } else {
                $updates['completed_at'] = null;
            }

            $item->update($updates);

            // If ticket was 'new', claiming an item promotes ticket to 'in_prep'
            $ticket = $item->ticket;
            if ($ticket && $ticket->status === 'new') {
                $ticket->update([
                    'status' => 'in_prep',
                    'started_at' => $ticket->started_at ?? now(),
                ]);
            }

            // If all items in the ticket are done, auto-advance ticket to 'ready'
            if ($ticket && $ticket->status === 'in_prep') {
                $unfinishedCount = $ticket->items()->where('status', '!=', 'done')->count();
                if ($unfinishedCount === 0) {
                    $ticket->update([
                        'status' => 'ready',
                        'ready_at' => now(),
                    ]);
                }
            }

            $item->load(['orderItem.meal', 'ticket']);

            event(new KitchenTicketItemStatusUpdated($item, $newStatus));

            return $item;
        });
    }
}
