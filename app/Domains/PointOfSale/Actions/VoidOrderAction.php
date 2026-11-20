<?php

namespace App\Domains\PointOfSale\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Events\OrderVoided;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use App\Support\SecurityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $rawManagerId = trim((string) ($data['manager_id'] ?? ''));

        // Resolve approving manager by case-insensitive emp_id first, falling back to numeric ID only when numeric
        $manager = null;
        if ($rawManagerId !== '') {
            $manager = User::whereRaw('UPPER(emp_id) = ?', [strtoupper($rawManagerId)])
                ->where('is_active', true)
                ->first();

            if (! $manager && ctype_digit($rawManagerId)) {
                $manager = User::where('id', (int) $rawManagerId)
                    ->where('is_active', true)
                    ->first();
            }
        }

        if (! $manager) {
            SecurityLogger::log('warning', 'VOID_ORDER_FAILED_MANAGER_NOT_FOUND', [
                'event' => 'void_order_failed_manager_not_found',
                'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                'actor_id' => Auth::id(),
                'ip' => request()->ip(),
                'order_id' => $data['order_id'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
            ]);
            throw new InvalidArgumentException("Manager record not found.");
        }

        $skipPin = (bool) ($data['skip_pin_check'] ?? false);
        if ($skipPin) {
            // Unauthenticated callers cannot use skip_pin_check
            if (! Auth::check() || ! Auth::user()) {
                SecurityLogger::log('warning', 'VOID_ORDER_UNAUTHENTICATED_SKIP_PIN', [
                    'event' => 'void_order_unauthenticated_skip_pin',
                    'actor' => 'guest',
                    'actor_id' => null,
                    'ip' => request()->ip(),
                    'order_id' => $data['order_id'] ?? null,
                    'manager_id' => $manager->id,
                ]);
                throw new InvalidArgumentException("Authentication required for back-office void.");
            }

            // Floor terminal sessions cannot bypass manager PIN verification
            if (session(SystemConstants::SESSION_AUTH_METHOD) === 'pin' || session('auth_method') === 'pin') {
                SecurityLogger::log('warning', 'VOID_ORDER_UNAUTHORIZED_FLOOR_BYPASS', [
                    'event' => 'void_order_unauthorized_floor_bypass',
                    'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                    'actor_id' => Auth::id(),
                    'ip' => request()->ip(),
                    'order_id' => $data['order_id'] ?? null,
                    'manager_id' => $manager->id,
                ]);
                throw new InvalidArgumentException("Floor terminal sessions cannot bypass manager PIN verification.");
            }

            // Reject audit spoofing where authenticated actor passes a different manager_id
            if ((int) Auth::id() !== (int) $manager->id) {
                SecurityLogger::log('warning', 'VOID_ORDER_AUDIT_SPOOFING_ATTEMPT', [
                    'event' => 'void_order_audit_spoofing_attempt',
                    'actor' => Auth::user()?->emp_id ?? (string) Auth::id(),
                    'actor_id' => Auth::id(),
                    'ip' => request()->ip(),
                    'order_id' => $data['order_id'] ?? null,
                    'manager_id' => $manager->id,
                ]);
                throw new InvalidArgumentException("Authenticated user must match approving manager.");
            }

            // Reject untrusted callers when authenticated user cannot approve refunds
            if (! Auth::user()->can('pos.refund')) {
                SecurityLogger::log('warning', 'VOID_ORDER_UNAUTHORIZED_BACKOFFICE_ATTEMPT', [
                    'event' => 'void_order_unauthorized_backoffice_attempt',
                    'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                    'actor_id' => Auth::id(),
                    'ip' => request()->ip(),
                    'order_id' => $data['order_id'] ?? null,
                    'manager_id' => $manager->id,
                ]);
                throw new InvalidArgumentException("Unauthorized back-office void attempt.");
            }
        } else {
            $pin = trim((string) ($data['manager_pin'] ?? ''));
            if (! $manager->verifyPin($pin)) {
                SecurityLogger::log('warning', 'VOID_ORDER_INVALID_MANAGER_PIN', [
                    'event' => 'void_order_invalid_manager_pin',
                    'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                    'actor_id' => Auth::id(),
                    'ip' => request()->ip(),
                    'order_id' => $data['order_id'] ?? null,
                    'manager_id' => $manager->id,
                ]);
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

        // Cross-branch isolation check: branch-scoped managers must have a non-null matching branch; Owner & Super-Admin are cross-branch
        if ($manager->isBranchScoped() && ($manager->branch_id === null || ($order->branch_id && (int) $manager->branch_id !== (int) $order->branch_id))) {
            SecurityLogger::log('warning', 'VOID_ORDER_CROSS_BRANCH_MISMATCH', [
                'event' => 'void_order_cross_branch_mismatch',
                'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                'actor_id' => Auth::id(),
                'ip' => request()->ip(),
                'order_id' => $order->id,
                'manager_id' => $manager->id,
            ]);
            throw new InvalidArgumentException("Manager does not belong to this branch.");
        }

        // If an authenticated branch-scoped actor (e.g. floor cashier) initiated the void, the target order must belong to their branch
        if (Auth::check() && Auth::user()?->isBranchScoped()) {
            $actorBranchId = Auth::user()->branch_id;
            if ($actorBranchId === null || ($order->branch_id && (int) $actorBranchId !== (int) $order->branch_id)) {
                SecurityLogger::log('warning', 'VOID_ORDER_ACTOR_CROSS_BRANCH_MISMATCH', [
                    'event' => 'void_order_actor_cross_branch_mismatch',
                    'actor' => Auth::user()?->emp_id ?? (string) Auth::id(),
                    'actor_id' => Auth::id(),
                    'ip' => request()->ip(),
                    'order_id' => $order->id,
                    'manager_id' => $manager->id,
                ]);
                throw new InvalidArgumentException("Manager does not belong to this branch.");
            }
        }

        // Approval requires the current refund permission, including executive roles.
        if (! $manager->can('pos.refund')) {
            SecurityLogger::log('warning', 'VOID_ORDER_UNAUTHORIZED_REFUND_PERMISSION', [
                'event' => 'void_order_unauthorized_refund_permission',
                'actor' => Auth::user()?->emp_id ?? (string) Auth::id() ?? 'guest',
                'actor_id' => Auth::id(),
                'ip' => request()->ip(),
                'order_id' => $order->id,
                'manager_id' => $manager->id,
            ]);
            throw new InvalidArgumentException("User is not authorized to approve voids.");
        }

        if (blank($data['reason'] ?? null)) {
            throw new InvalidArgumentException("A valid cancellation reason is required.");
        }

        $voidedOrder = DB::transaction(function () use ($manager, $data) {
            // Acquire pessimistic row lock on target order to serialize voids and prevent double stock restoration
            $order = Order::where('id', $data['order_id'])->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::CANCELLED->value) {
                throw new InvalidArgumentException("Order #{$order->order_number} is already cancelled.");
            }

            // Re-verify branch isolation under row lock
            if ($manager->isBranchScoped() && ($manager->branch_id === null || ($order->branch_id && (int) $manager->branch_id !== (int) $order->branch_id))) {
                throw new InvalidArgumentException("Manager does not belong to this branch.");
            }
            $previousStatus = $order->status;
            $auditStamp = "[VOIDED by {$manager->name} ({$manager->emp_id}) on " . now(config('app.business_timezone'))->toIso8601String() . ": {$data['reason']}]";
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
                    $branchOverride = DB::table('meal_branch_availabilities')
                        ->where('meal_id', $item->meal_id)
                        ->where('branch_id', $order->branch_id)
                        ->where('track_stock', true)
                        ->first();

                    if ($branchOverride) {
                        DB::table('meal_branch_availabilities')
                            ->where('id', $branchOverride->id)
                            ->increment('stock_quantity', $item->quantity);
                    } else {
                        DB::table('meals')
                            ->where('id', $item->meal_id)
                            ->where('track_stock', true)
                            ->increment('stock_quantity', $item->quantity);
                    }
                }
            }

            $voidContext = [
                'event' => 'ORDER_VOIDED',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'manager_id' => $manager->id,
                'actor' => $manager->emp_id ?? (string) $manager->id,
                'actor_id' => $manager->id,
                'cashier_id' => $data['cashier_id'] ?? null,
                'ip' => request()->ip(),
                'previous_status' => $previousStatus,
                'reason' => $data['reason'],
                'voided_at' => now(config('app.business_timezone'))->toIso8601String(),
            ];

            Log::info("ORDER_VOIDED", $voidContext);
            SecurityLogger::log('warning', 'ORDER_VOID_AUTHORIZED', $voidContext);

            event(new OrderVoided($order, $manager, $data['reason']));

            $order->refresh();

            return $order;
        });

        if ($voidedOrder->branch_id) {
            Cache::forget("branch_{$voidedOrder->branch_id}_order_progress_counts");
            Meal::bumpMenuVersionForSingleBranch((int) $voidedOrder->branch_id);
        }

        return $voidedOrder;
    }
}
