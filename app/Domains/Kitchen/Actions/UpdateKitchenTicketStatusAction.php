<?php

namespace App\Domains\Kitchen\Actions;

use App\Domains\Kitchen\Actions\Concerns\AuthorizesKitchenActions;
use App\Domains\Kitchen\Events\KitchenTicketStatusUpdated;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Enums\KitchenTicketItemStatus;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateKitchenTicketStatusAction
{
    use AuthorizesKitchenActions;

    /**
     * Transition ticket to a new status and sync order state.
     */
    public function execute(KitchenTicket $ticket, string $newStatus): KitchenTicket
    {
        $this->authorizeKitchenAction($ticket, 'update kitchen ticket status');

        if (! in_array($newStatus, KitchenTicketStatus::values(), true)) {
            throw new InvalidArgumentException("Invalid kitchen ticket status: {$newStatus}");
        }

        return DB::transaction(function () use ($ticket, $newStatus) {
            $order = Order::whereKey($ticket->order_id)->lockForUpdate()->firstOrFail();
            $ticket = KitchenTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($order->status === OrderStatus::CANCELLED->value || $ticket->status === KitchenTicketStatus::CANCELLED->value) {
                throw new InvalidArgumentException('Cancelled orders cannot change kitchen status.');
            }
            $ticket->setRelation('order', $order);
            $previousStatus = $ticket->status;
            $updates = ['status' => $newStatus];

            if ($newStatus === KitchenTicketStatus::IN_PREP->value && ! $ticket->started_at) {
                $updates['started_at'] = now();
            } elseif ($newStatus === KitchenTicketStatus::READY->value) {
                $updates['ready_at'] = now();
                // When ticket is marked ready, mark all unfinished items as done
                $ticket->items()->where('status', '!=', KitchenTicketItemStatus::DONE->value)->update([
                    'status' => KitchenTicketItemStatus::DONE->value,
                    'completed_at' => now(),
                ]);
            } elseif ($newStatus === KitchenTicketStatus::SERVED->value) {
                $updates['served_at'] = now();
                // When served, sync associated order to completed
                if ($ticket->order) {
                    $ticket->order->update([
                        'status' => OrderStatus::COMPLETED->value,
                        'completed_at' => now(),
                        'stock_consumed_at' => $order->stock_consumed_at ?? now(),
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
