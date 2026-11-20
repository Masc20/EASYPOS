<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Actions\Concerns\AuthorizesKitchenActions;
use App\Domains\Kitchen\Events\KitchenTicketItemStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Enums\KitchenTicketItemStatus;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ToggleKitchenItemStatusAction
{
    use AuthorizesKitchenActions;

    /**
     * Toggle item between cooking/pending and done.
     */
    public function execute(KitchenTicketItem $item, ?string $forceStatus = null): KitchenTicketItem
    {
        $this->authorizeKitchenAction($item->ticket, 'modify kitchen ticket items');

        if ($forceStatus !== null && ! in_array($forceStatus, KitchenTicketItemStatus::values(), true)) {
            throw new InvalidArgumentException("Invalid kitchen ticket item status: {$forceStatus}");
        }

        return DB::transaction(function () use ($item, $forceStatus) {
            $order = Order::whereKey($item->ticket->order_id)->lockForUpdate()->firstOrFail();
            $ticket = KitchenTicket::whereKey($item->ticket_id)->lockForUpdate()->firstOrFail();
            $item = KitchenTicketItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($order->status === OrderStatus::CANCELLED->value || $ticket->status === KitchenTicketStatus::CANCELLED->value) {
                throw new InvalidArgumentException('Cancelled orders cannot change preparation status.');
            }
            $item->setRelation('ticket', $ticket);
            if ($forceStatus) {
                $newStatus = $forceStatus;
            } else {
                $newStatus = ($item->status === KitchenTicketItemStatus::DONE->value)
                    ? KitchenTicketItemStatus::COOKING->value
                    : KitchenTicketItemStatus::DONE->value;
            }

            $updates = ['status' => $newStatus];
            if ($newStatus === KitchenTicketItemStatus::DONE->value) {
                $updates['completed_at'] = now();
            } else {
                $updates['completed_at'] = null;
            }

            $item->update($updates);

            // If ticket was 'new', claiming an item promotes ticket to 'in_prep'
            $ticket = $item->ticket;
            if ($ticket && $ticket->status === KitchenTicketStatus::NEW->value) {
                $ticket->update([
                    'status' => KitchenTicketStatus::IN_PREP->value,
                    'started_at' => $ticket->started_at ?? now(),
                ]);
            }

            // If all items in the ticket are done, auto-advance ticket to 'ready'
            if ($ticket && $ticket->status === KitchenTicketStatus::IN_PREP->value) {
                $unfinishedCount = $ticket->items()->where('status', '!=', KitchenTicketItemStatus::DONE->value)->count();
                if ($unfinishedCount === 0) {
                    $ticket->update([
                        'status' => KitchenTicketStatus::READY->value,
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
