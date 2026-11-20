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
            // Use the same order -> ticket lock order as kitchen status transitions.
            $tickets = DB::table('kitchen_tickets')->where('order_id', $order->id)->lockForUpdate()->get();
            $wasConsumed = $order->stock_consumed_at !== null || $order->completed_at !== null
                || $order->status === OrderStatus::COMPLETED->value
                || $tickets->contains(fn ($ticket) => $ticket->status === KitchenTicketStatus::SERVED->value || $ticket->served_at !== null);
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

            // Return only reserved portions. Served meals remain physically consumed.
            $globalStockChanged = ! $wasConsumed && $this->restoreReservedPortions($order);
            DB::afterCommit(function () use ($order, $globalStockChanged): void {
                Cache::forget("branch_{$order->branch_id}_order_progress_counts");
                if ($globalStockChanged) {
                    Meal::bumpMenuVersionForBranch(null);
                } elseif ($order->branch_id !== null) {
                    Meal::bumpMenuVersionForSingleBranch((int) $order->branch_id);
                }
            });

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

            $order->refresh();

            event(new OrderVoided($order, $manager, $data['reason']));

            return $order;
        });

        return $voidedOrder;
    }

    private function restoreReservedPortions(Order $order): bool
    {
        $globalStockChanged = false;
        $items = DB::table('order_items')->where('order_id', $order->id)->whereNotNull('meal_id')
            ->selectRaw('meal_id, stock_source, SUM(quantity) AS quantity')
            ->groupBy('meal_id', 'stock_source')->orderBy('meal_id')->get();

        foreach ($items as $item) {
            if ($item->stock_source === 'none' || $item->quantity <= 0) {
                continue;
            }
            // Match checkout's deterministic meal -> branch-override lock order.
            $meal = Meal::withTrashed()->whereKey($item->meal_id)->lockForUpdate()->first();
            if (! $meal || ($meal->branch_id !== null && (int) $meal->branch_id !== (int) $order->branch_id)) {
                continue;
            }
            $override = DB::table('meal_branch_availabilities')->where('meal_id', $item->meal_id)
                ->where('branch_id', $order->branch_id)->lockForUpdate()->first();
            $source = $item->stock_source;
            if ($source === null) {
                // Historical orders predate deduction snapshots. Honor an explicit unlimited override.
                if ($meal->branch_id === null && $override !== null && $override->track_stock !== null) {
                    $source = $override->track_stock ? 'branch' : 'none';
                } else {
                    $source = $meal->track_stock ? 'meal' : 'none';
                }
            }
            if ($source === 'branch' && $override !== null) {
                $stock = $override;
                $query = DB::table('meal_branch_availabilities')->where('id', $override->id);
            } elseif ($source === 'meal') {
                $stock = $meal;
                $query = DB::table('meals')->where('id', $meal->id);
                $globalStockChanged = $globalStockChanged || $meal->branch_id === null;
            } else {
                continue;
            }
            $remaining = (int) $stock->stock_quantity + (int) $item->quantity;
            $changes = ['stock_quantity' => $remaining, 'updated_at' => now()];
            // Preserve manual 86 holds on dishes that still had positive portions.
            if ($stock->track_stock && (int) $stock->stock_quantity <= 0 && $remaining > 0) {
                $changes['is_available'] = true;
            }
            $query->update($changes);
        }

        return $globalStockChanged;
    }

}
