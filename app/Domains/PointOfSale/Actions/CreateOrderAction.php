<?php

namespace App\Domains\PointOfSale\Actions;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Events\OrderCreated;
use App\Domains\PointOfSale\Models\BranchDailySequence;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateOrderAction
{
    /**
     * Create a new POS Order with its items in a single atomic transaction.
     *
     * @param array{
     *     branch_id: int,
     *     cashier_id: int,
     *     order_type: string,
     *     table_number?: ?string,
     *     customer_name?: ?string,
     *     discount_amount?: float|int,
     *     notes?: ?string,
     *     items: array<int, array{
     *         meal_id: int,
     *         quantity: int,
     *         special_instructions?: ?string
     *     }>
     * } $data
     */
    public function execute(array $data): Order
    {
        if (empty($data['items'])) {
            throw new InvalidArgumentException('Cannot create an order without items.');
        }

        // Validate order_type against OrderType::cases() enum
        $rawOrderType = $data['order_type'] ?? OrderType::DINE_IN->value;
        $orderTypeEnum = OrderType::tryFrom($rawOrderType);
        if (! $orderTypeEnum) {
            throw new InvalidArgumentException("Invalid order type: {$rawOrderType}");
        }
        $orderType = $orderTypeEnum->value;

        // Validate string length bounds
        $tableNumber = ($orderType === OrderType::DINE_IN->value) ? ($data['table_number'] ?? null) : null;
        if (! empty($data['table_number']) && mb_strlen((string) $data['table_number']) > 50) {
            throw new InvalidArgumentException('Table number cannot exceed 50 characters.');
        }

        $customerName = $data['customer_name'] ?? null;
        if (! empty($customerName) && mb_strlen((string) $customerName) > 150) {
            throw new InvalidArgumentException('Customer name cannot exceed 150 characters.');
        }

        $notes = $data['notes'] ?? null;
        if (! empty($notes) && mb_strlen((string) $notes) > 500) {
            throw new InvalidArgumentException('Notes cannot exceed 500 characters.');
        }

        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $orderType, $tableNumber, $customerName, $notes) {
                    $branch = Branch::findOrFail($data['branch_id']);
                    $branchCode = $branch->code ?? 'B01';
                    $dateStr = today()->format('Ymd');

                    // Sequential order number generation via atomic BranchDailySequence row-level counter
                    $nextSequence = BranchDailySequence::getNextSequence($branch->id, 'order', today()->toDateString());
                    $orderNumber = sprintf('%s-%s-%04d', $branchCode, $dateStr, $nextSequence);

                    while (Order::where('order_number', $orderNumber)->exists()) {
                        $nextSequence = BranchDailySequence::getNextSequence($branch->id, 'order', today()->toDateString());
                        $orderNumber = sprintf('%s-%s-%04d', $branchCode, $dateStr, $nextSequence);
                    }

                    // 1. Group/consolidate required stock quantities per distinct meal_id across cart items
                    $consolidatedQuantities = [];
                    foreach ($data['items'] as $item) {
                        if (! isset($item['quantity']) || ! is_numeric($item['quantity']) || (int) $item['quantity'] <= 0 || (int) $item['quantity'] != $item['quantity']) {
                            throw new InvalidArgumentException('Item quantity must be a positive integer greater than zero.');
                        }
                        $quantity = (int) $item['quantity'];
                        if ($quantity > 1000) {
                            throw new InvalidArgumentException('Item quantity exceeds maximum allowed limit (1000).');
                        }

                        if (! empty($item['special_instructions']) && mb_strlen((string) $item['special_instructions']) > 255) {
                            throw new InvalidArgumentException('Special instructions cannot exceed 255 characters.');
                        }

                        $mId = (int) ($item['meal_id'] ?? 0);
                        $consolidatedQuantities[$mId] = ($consolidatedQuantities[$mId] ?? 0) + $quantity;
                    }

                    // 2. Sort distinct meal IDs in ascending order to eliminate MySQL InnoDB deadlock risk (ERROR 1213)
                    $sortedMealIds = array_keys($consolidatedQuantities);
                    sort($sortedMealIds, SORT_NUMERIC);

                    // 3. Acquire pessimistic locks in deterministic ascending order
                    $meals = Meal::whereIn('id', $sortedMealIds)
                        ->orderBy('id', 'asc')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    // 4. Validate existence, branch tenancy, availability, and portion stock per distinct dish
                    foreach ($sortedMealIds as $mealId) {
                        $meal = $meals->get($mealId);
                        if (! $meal) {
                            throw new InvalidArgumentException("Meal ID {$mealId} not found.");
                        }

                        if ($meal->branch_id !== null && (int) $meal->branch_id !== (int) $branch->id) {
                            throw new InvalidArgumentException("Meal '{$meal->name}' is not available for this branch.");
                        }

                        if ($meal->isSoldOut()) {
                            throw new InvalidArgumentException("Meal '{$meal->name}' is marked as 86'd / Sold Out.");
                        }

                        if ($meal->track_stock) {
                            $requiredQty = $consolidatedQuantities[$mealId];
                            if ($meal->stock_quantity < $requiredQty) {
                                throw new InvalidArgumentException("Insufficient portions left for '{$meal->name}'. Only {$meal->stock_quantity} available.");
                            }
                        }
                    }

                    // 5. Batch stock update per distinct tracked dish in a single consolidated operation
                    foreach ($sortedMealIds as $mealId) {
                        $meal = $meals->get($mealId);
                        if ($meal && $meal->track_stock) {
                            $requiredQty = $consolidatedQuantities[$mealId];
                            $newStock = $meal->stock_quantity - $requiredQty;
                            $meal->update([
                                'stock_quantity' => $newStock,
                                'is_available' => $newStock > 0,
                            ]);
                        }
                    }

                    // 6. Compute subtotals and format order item rows for batch insert
                    $subtotal = 0.00;
                    $now = now();
                    $orderItemsData = [];

                    foreach ($data['items'] as $item) {
                        $mealId = (int) $item['meal_id'];
                        $meal = $meals->get($mealId);
                        $quantity = (int) $item['quantity'];
                        $unitPrice = (float) $meal->price;
                        $itemSubtotal = $unitPrice * $quantity;
                        $subtotal += $itemSubtotal;

                        $selectedOptions = $item['selected_options'] ?? [];
                        if (! is_string($selectedOptions)) {
                            $selectedOptions = json_encode($selectedOptions ?: []);
                        }

                        $orderItemsData[] = [
                            'meal_id' => $meal->id,
                            'meal_name' => $meal->name,
                            'unit_price' => $unitPrice,
                            'quantity' => $quantity,
                            'subtotal' => $itemSubtotal,
                            'special_instructions' => $item['special_instructions'] ?? null,
                            'selected_options' => $selectedOptions,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $subtotal = round($subtotal, 2);

                    // Recompute & Authorize Discount Server-Side
                    $discountType = $data['discount_type'] ?? 'none';
                    $user = auth()->user() ?? (isset($data['cashier_id']) ? User::find($data['cashier_id']) : null);
                    $discountAmount = 0.00;

                    switch ($discountType) {
                        case 'senior_pwd':
                            // Statutory 20% discount computed server-side based on database prices
                            // Never trust client-supplied discount amount for Senior/PWD
                            $discountAmount = round($subtotal * SystemConstants::SENIOR_PWD_DISCOUNT_RATE, 2);
                            break;

                        case 'custom':
                            if (! $user || (! $user->isOwner() && ! $user->can('pos.discount') && ! $user->canManageBranchRoles())) {
                                throw new InvalidArgumentException('User is not authorized to apply custom discounts.');
                            }
                            $requestedCustom = (float) ($data['custom_discount'] ?? $data['discount_amount'] ?? 0);
                            if ($requestedCustom < 0 || $requestedCustom > $subtotal) {
                                throw new InvalidArgumentException("Custom discount must be between ₱0.00 and ₱{$subtotal}.");
                            }
                            $discountAmount = round($requestedCustom, 2);
                            break;

                        case 'none':
                            $discountAmount = 0.00;
                            break;

                        default:
                            throw new InvalidArgumentException("Invalid discount type: {$discountType}");
                    }

                    $discountAmount = min($subtotal, max(0.00, $discountAmount));
                    $taxAmount = 0.00; // In Philippine dining, prices are VAT-inclusive
                    $totalAmount = max(0.00, round($subtotal - $discountAmount + $taxAmount, 2));

                    // Create Order
                    $order = Order::create([
                        'order_number' => $orderNumber,
                        'branch_id' => $branch->id,
                        'cashier_id' => $data['cashier_id'],
                        'order_type' => $orderType,
                        'table_number' => $tableNumber,
                        'customer_name' => $customerName,
                        'subtotal' => $subtotal,
                        'discount_amount' => $discountAmount,
                        'tax_amount' => $taxAmount,
                        'total_amount' => $totalAmount,
                        'status' => OrderStatus::PENDING->value,
                        'payment_status' => PaymentStatus::UNPAID->value,
                        'notes' => $notes,
                    ]);

                    // Save line items via batch insert in a single query
                    foreach ($orderItemsData as &$row) {
                        $row['order_id'] = $order->id;
                    }
                    unset($row);

                    OrderItem::insert($orderItemsData);

            DB::afterCommit(function () use ($order) {
                event(new OrderCreated($order));
            });

            return $order;
                });
            } catch (QueryException $e) {
                $isOrderNumberCollision = str_contains(strtolower($e->getMessage()), 'order_number')
                    || str_contains($e->getMessage(), 'orders_order_number_unique')
                    || (str_contains($e->getMessage(), 'UNIQUE') && str_contains(strtolower($e->getMessage()), 'order'));

                if ($isOrderNumberCollision && $attempt < $maxAttempts) {
                    usleep(random_int(10000, 50000));
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Failed to generate a unique order number after maximum retry attempts.');
    }
}
