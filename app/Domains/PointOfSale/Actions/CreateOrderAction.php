<?php

namespace App\Domains\PointOfSale\Actions;

use App\Domains\Branches\Models\Branch;
use App\Domains\Menu\Models\Meal;
use App\Domains\PointOfSale\Events\OrderCreated;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
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

        return DB::transaction(function () use ($data) {
            $branch = Branch::findOrFail($data['branch_id']);
            $branchCode = $branch->code ?? 'B01';
            $dateStr = now()->format('Ymd');

            // Sequential order number generation for the branch today
            $todayCount = Order::where('branch_id', $branch->id)
                ->whereDate('created_at', today())
                ->lockForUpdate()
                ->count();
            
            $sequence = str_pad((string) ($todayCount + 1), 4, '0', STR_PAD_LEFT);
            $orderNumber = "{$branchCode}-{$dateStr}-{$sequence}";

            $orderType = $data['order_type'] ?? 'dine_in';
            $tableNumber = ($orderType === 'dine_in') ? ($data['table_number'] ?? null) : null;
            $customerName = $data['customer_name'] ?? null;
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $notes = $data['notes'] ?? null;

            // Fetch and lock meal models to prevent race conditions on daily stock
            $mealIds = array_column($data['items'], 'meal_id');
            $meals = Meal::whereIn('id', $mealIds)->lockForUpdate()->get()->keyBy('id');

            $subtotal = 0.00;
            $orderItemsData = [];

            foreach ($data['items'] as $item) {
                $mealId = $item['meal_id'];
                $meal = $meals->get($mealId);

                if (! $meal) {
                    throw new InvalidArgumentException("Meal ID {$mealId} not found.");
                }

                if (! $meal->is_available) {
                    throw new InvalidArgumentException("Meal '{$meal->name}' is marked as 86'd / Sold Out.");
                }

                $quantity = max(1, (int) $item['quantity']);
                $unitPrice = (float) $meal->price;
                $itemSubtotal = $unitPrice * $quantity;
                $subtotal += $itemSubtotal;

                // Daily stock portions countdown
                if ($meal->track_stock) {
                    if ($meal->stock_quantity < $quantity) {
                        throw new InvalidArgumentException("Insufficient portions left for '{$meal->name}'. Only {$meal->stock_quantity} available.");
                    }
                    $meal->decrement('stock_quantity', $quantity);
                    if ($meal->stock_quantity <= 0) {
                        $meal->update(['is_available' => false]);
                    }
                }

                $orderItemsData[] = [
                    'meal_id' => $meal->id,
                    'meal_name' => $meal->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'subtotal' => $itemSubtotal,
                    'special_instructions' => $item['special_instructions'] ?? null,
                    'selected_options' => $item['selected_options'] ?? [],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Calculations
            $discountAmount = min($subtotal, max(0.00, $discountAmount));
            $taxAmount = 0.00; // In Philippine dining, prices are VAT-inclusive
            $totalAmount = max(0.00, $subtotal - $discountAmount + $taxAmount);

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
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'notes' => $notes,
            ]);

            // Save line items via Eloquent relation so json casts are properly handled
            $order->items()->createMany($orderItemsData);

            event(new OrderCreated($order));

            return $order;
        });
    }
}
