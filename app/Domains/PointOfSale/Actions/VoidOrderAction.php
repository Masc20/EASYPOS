<?php

namespace App\Domains\PointOfSale\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Events\OrderVoided;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class VoidOrderAction
{
    /**
     * Void or cancel an order with manager PIN authorization or direct admin action.
     *
     * @param array{
     *     order_id: int,
     *     manager_id: int|string,
     *     manager_pin?: ?string,
     *     reason: string,
     *     cashier_id?: ?int,
     *     skip_pin_check?: bool
     * } $data
     */
    public function execute(array $data): Order
    {
        // Resolve approving manager by emp_id first, falling back to numeric ID
        $manager = User::where('emp_id', $data['manager_id'])
            ->where('is_active', true)
            ->first();

        if (! $manager) {
            $manager = User::where('id', $data['manager_id'])
                ->where('is_active', true)
                ->first();
        }

        if (! $manager) {
            throw new InvalidArgumentException("Manager record not found.");
        }

        $skipPin = (bool) ($data['skip_pin_check'] ?? false);
        if ($skipPin) {
            // Floor terminal sessions cannot bypass manager PIN verification
            if (session(SystemConstants::SESSION_AUTH_METHOD) === 'pin' || session('auth_method') === 'pin') {
                throw new InvalidArgumentException("Floor terminal sessions cannot bypass manager PIN verification.");
            }

            // Reject untrusted callers when authenticated user cannot approve refunds
            if (Auth::check() && ! Auth::user()->isOwner() && ! Auth::user()->can('pos.refund')) {
                throw new InvalidArgumentException("Unauthorized back-office void attempt.");
            }
        } else {
            $pin = (string) ($data['manager_pin'] ?? '');
            if (! $manager->verifyPin($pin)) {
                throw new InvalidArgumentException("Invalid manager PIN.");
            }
        }

        // Fetch order record
        $order = Order::find($data['order_id']);
        if (! $order) {
            throw new InvalidArgumentException("Order #{$data['order_id']} not found.");
        }

        if ($order->status === OrderStatus::CANCELLED->value) {
            throw new InvalidArgumentException("Order #{$order->order_number} is already cancelled.");
        }

        // Cross-branch isolation check: manager must belong to same branch unless global Owner / Super-Admin
        if (! $manager->isOwner() && $order->branch_id && $manager->branch_id !== $order->branch_id) {
            throw new InvalidArgumentException("Manager does not belong to this branch.");
        }

        // Permission check: must possess pos.refund permission or be an Owner / Super-Admin
        if (! $manager->isOwner() && ! $manager->can('pos.refund')) {
            throw new InvalidArgumentException("User is not authorized to approve voids.");
        }

        if (blank($data['reason'] ?? null)) {
            throw new InvalidArgumentException("A valid cancellation reason is required.");
        }

        return DB::transaction(function () use ($manager, $data) {
            // Acquire pessimistic row lock on target order to serialize voids and prevent double stock restoration
            $order = Order::where('id', $data['order_id'])->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::CANCELLED->value) {
                throw new InvalidArgumentException("Order #{$order->order_number} is already cancelled.");
            }

            // Re-verify branch isolation under row lock
            if (! $manager->isOwner() && $order->branch_id && $manager->branch_id !== $order->branch_id) {
                throw new InvalidArgumentException("Manager does not belong to this branch.");
            }
            $previousStatus = $order->status;
            $auditStamp = "[VOIDED by {$manager->name} ({$manager->emp_id}) on " . now()->toDateTimeString() . ": {$data['reason']}]";
            $updatedNotes = trim(($order->notes ? $order->notes . "\n" : '') . $auditStamp);

            // 1. Update Order status and append audit note
            DB::table('orders')->where('id', $order->id)->update([
                'status' => OrderStatus::CANCELLED->value,
                'payment_status' => PaymentStatus::REFUNDED->value,
                'notes' => $updatedNotes,
                'updated_at' => now(),
            ]);

            // 2. Mark payments as refunded
            DB::table('payments')->where('order_id', $order->id)->update([
                'status' => PaymentStatus::REFUNDED->value,
                'updated_at' => now(),
            ]);

            // 3. Cancel any linked kitchen tickets
            DB::table('kitchen_tickets')->where('order_id', $order->id)->update([
                'status' => KitchenTicketStatus::CANCELLED->value,
                'served_at' => null,
                'updated_at' => now(),
            ]);

            // 4. Return inventory stock for trackable items
            $items = DB::table('order_items')->where('order_id', $order->id)->get(['meal_id', 'quantity']);
            foreach ($items as $item) {
                if ($item->meal_id) {
                    DB::table('meals')
                        ->where('id', $item->meal_id)
                        ->where('track_stock', true)
                        ->increment('stock_quantity', $item->quantity);
                }
            }

            Log::info("ORDER_VOIDED", [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'manager_id' => $manager->id,
                'cashier_id' => $data['cashier_id'] ?? null,
                'previous_status' => $previousStatus,
                'reason' => $data['reason'],
                'voided_at' => now()->toDateTimeString(),
            ]);

            event(new OrderVoided($order, $manager, $data['reason']));

            $order->refresh();

            return $order;
        });
    }
}
