<?php

namespace Tests\Concerns;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use App\Domains\PointOfSale\Models\Order;
use App\Domains\PointOfSale\Models\OrderItem;
use App\Domains\PointOfSale\Models\Payment;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\OrderType;
use App\Support\Enums\PaymentMethod;
use App\Support\Enums\PaymentStatus;
use Carbon\Carbon;

trait CreatesPosAndKitchenFixtures
{
    /**
     * Create a MealCategory and Meal scoped to the given branch (or null for global).
     *
     * @param  array<string, mixed>  $mealOverrides
     * @param  array<string, mixed>  $catOverrides
     * @return array{category: MealCategory, meal: Meal}
     */
    protected function createBranchCategoryAndMeal(
        ?Branch $branch,
        array $mealOverrides = [],
        array $catOverrides = []
    ): array {
        static $seq = 0;
        $seq++;
        $prefix = $branch?->code ?? 'GLB';
        $tag = strtoupper($prefix).'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        $category = MealCategory::create(array_merge([
            'branch_id' => $branch?->id,
            'name' => "E2E Category {$tag}",
            'slug' => 'e2e-cat-'.strtolower($tag).'-'.uniqid(),
            'display_order' => $seq,
            'is_active' => true,
        ], $catOverrides));

        $meal = Meal::create(array_merge([
            'branch_id' => $branch?->id,
            'category_id' => $category->id,
            'name' => "E2E Dish {$tag}",
            'menu_code' => 'E2E-'.$seq.'-'.substr(strtoupper(md5((string) microtime(true).$seq)), 0, 4),
            'price' => 180.00,
            'is_available' => true,
            'track_stock' => false,
            'stock_quantity' => null,
            'is_active' => true,
        ], $mealOverrides));

        return [
            'category' => $category,
            'meal' => $meal,
        ];
    }

    /**
     * Create a paid Order and dispatched KitchenTicket using real domain actions
     * (exercising stock deduction, BranchDailySequence allocation, and events).
     *
     * @param  array<string, mixed>  $orderAttributes
     */
    protected function createPaidTicketViaActions(
        User $cashier,
        array $orderAttributes = [],
        ?Meal $meal = null,
        int $quantity = 1,
        string $specialInstructions = ''
    ): KitchenTicket {
        $branchId = (int) ($orderAttributes['branch_id'] ?? $cashier->branch_id ?? 1);
        $branch = Branch::find($branchId);

        if ($meal === null) {
            $meal = Meal::forBranch($branchId)
                ->where('is_active', true)
                ->where('is_available', true)
                ->first()
                ?? $this->createBranchCategoryAndMeal($branch)['meal'];
        }

        $orderType = (string) ($orderAttributes['order_type'] ?? OrderType::DINE_IN->value);
        $items = $orderAttributes['items'] ?? [
            [
                'meal_id' => $meal->id,
                'quantity' => $quantity,
                'special_instructions' => $specialInstructions !== '' ? $specialInstructions : null,
            ],
        ];

        $orderPayload = array_merge([
            'branch_id' => $branchId,
            'cashier_id' => $cashier->id,
            'order_type' => $orderType,
            'table_number' => $orderType === OrderType::DINE_IN->value
                ? ($orderAttributes['table_number'] ?? '5')
                : null,
            'customer_name' => $orderAttributes['customer_name'] ?? null,
            'discount_type' => $orderAttributes['discount_type'] ?? 'none',
            'notes' => $orderAttributes['notes'] ?? null,
            'items' => $items,
        ], array_diff_key($orderAttributes, array_flip([
            'payment_method',
            'amount_tendered',
            'reference_number',
        ])));

        $order = app(CreateOrderAction::class)->execute($orderPayload);

        $paymentMethod = $orderAttributes['payment_method'] ?? PaymentMethod::CASH->value;
        $amountTendered = $orderAttributes['amount_tendered']
            ?? ((float) $order->total_amount + ($paymentMethod === PaymentMethod::CASH->value ? 50.00 : 0.00));

        $paymentResult = app(ProcessPaymentAction::class)->execute($order, [
            'cashier_id' => $cashier->id,
            'payment_method' => $paymentMethod,
            'amount_tendered' => $amountTendered,
            'reference_number' => $orderAttributes['reference_number'] ?? null,
        ]);

        return $paymentResult['kitchen_ticket'];
    }

    /**
     * Create a complete Order + OrderItem + Payment + KitchenTicket + KitchenTicketItem fixture directly.
     *
     * @param  array<string, mixed>  $orderOverrides
     * @param  array<string, mixed>  $ticketOverrides
     * @param  array<string, mixed>  $itemOverrides
     * @return array{order: Order, order_item: OrderItem, payment: Payment, ticket: KitchenTicket, ticket_item: KitchenTicketItem}
     */
    protected function createOrderWithKitchenTicket(
        Branch|int $branch,
        User $cashier,
        ?Meal $meal = null,
        array $orderOverrides = [],
        array $ticketOverrides = [],
        array $itemOverrides = []
    ): array {
        static $orderSeq = 0;
        $orderSeq++;

        $branchModel = $branch instanceof Branch ? $branch : Branch::findOrFail($branch);

        if ($meal === null) {
            $meal = Meal::forBranch($branchModel->id)->first()
                ?? $this->createBranchCategoryAndMeal($branchModel)['meal'];
        }

        $suffix = str_pad((string) $orderSeq, 4, '0', STR_PAD_LEFT).'-'.substr(md5((string) microtime(true).$orderSeq), 0, 4);

        $quantity = (int) ($itemOverrides['quantity'] ?? 1);
        $unitPrice = (float) ($itemOverrides['unit_price'] ?? $meal->price);
        $lineSubtotal = round($unitPrice * $quantity, 2);
        $orderCreatedAt = $orderOverrides['created_at'] ?? now();

        $order = Order::create(array_merge([
            'order_number' => "{$branchModel->code}-E2E-{$suffix}",
            'branch_id' => $branchModel->id,
            'cashier_id' => $cashier->id,
            'order_type' => OrderType::DINE_IN->value,
            'subtotal' => $lineSubtotal,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => $lineSubtotal,
            'status' => OrderStatus::IN_KITCHEN->value,
            'payment_status' => PaymentStatus::PAID->value,
            'created_at' => $orderCreatedAt,
            'updated_at' => $orderOverrides['updated_at'] ?? $orderCreatedAt,
        ], $orderOverrides));

        $orderItem = OrderItem::create(array_merge([
            'order_id' => $order->id,
            'meal_id' => $meal->id,
            'meal_name' => $meal->name,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $lineSubtotal,
            'selected_options' => [],
            'created_at' => $orderCreatedAt,
            'updated_at' => $orderOverrides['updated_at'] ?? $orderCreatedAt,
        ], $itemOverrides));

        $paymentStatus = $order->payment_status === PaymentStatus::REFUNDED->value
            ? PaymentStatus::REFUNDED->value
            : 'successful';

        $payment = Payment::create([
            'order_id' => $order->id,
            'branch_id' => $branchModel->id,
            'cashier_id' => $cashier->id,
            'payment_method' => PaymentMethod::CASH->value,
            'amount' => (float) $order->total_amount,
            'amount_tendered' => (float) $order->total_amount,
            'change_given' => 0.00,
            'status' => $paymentStatus,
            'created_at' => $orderCreatedAt,
            'updated_at' => $orderOverrides['updated_at'] ?? $orderCreatedAt,
        ]);

        $ticketCreatedAt = $ticketOverrides['created_at'] ?? $orderCreatedAt;

        $ticket = KitchenTicket::create(array_merge([
            'ticket_number' => "KDS-{$branchModel->code}-{$suffix}",
            'order_id' => $order->id,
            'branch_id' => $branchModel->id,
            'cook_id' => null,
            'status' => KitchenTicketStatus::NEW->value,
            'priority' => 'normal',
            'started_at' => null,
            'ready_at' => null,
            'served_at' => null,
            'created_at' => $ticketCreatedAt,
            'updated_at' => $ticketOverrides['updated_at'] ?? $ticketCreatedAt,
        ], $ticketOverrides));

        $ticketItem = KitchenTicketItem::create([
            'ticket_id' => $ticket->id,
            'order_item_id' => $orderItem->id,
            'status' => 'pending',
            'completed_at' => null,
            'created_at' => $ticketCreatedAt,
            'updated_at' => $ticketOverrides['updated_at'] ?? $ticketCreatedAt,
        ]);

        return [
            'order' => $order,
            'order_item' => $orderItem,
            'payment' => $payment,
            'ticket' => $ticket,
            'ticket_item' => $ticketItem,
        ];
    }

    /**
     * Batch-create active or served orders and kitchen tickets for a branch.
     *
     * @param  array<string, mixed>  $orderOverrides
     * @param  array<string, mixed>  $ticketOverrides
     * @return array<int, KitchenTicket>
     */
    protected function createTicketsForBranch(
        int $count,
        int $branchId,
        User $cashier,
        string $status = 'new',
        ?Carbon $createdAt = null,
        array $orderOverrides = [],
        array $ticketOverrides = []
    ): array {
        $branch = Branch::findOrFail($branchId);
        $meal = Meal::forBranch($branchId)->first()
            ?? $this->createBranchCategoryAndMeal($branch)['meal'];
        $time = $createdAt ?? now();

        $tickets = [];

        for ($i = 1; $i <= $count; $i++) {
            $mergedOrderOverrides = array_merge([
                'table_number' => (string) (($i % 20) + 1),
                'customer_name' => "Customer {$i}",
                'status' => $status === KitchenTicketStatus::SERVED->value
                    ? OrderStatus::COMPLETED->value
                    : OrderStatus::IN_KITCHEN->value,
                'created_at' => $time,
                'updated_at' => $time,
            ], $orderOverrides);

            $mergedTicketOverrides = array_merge([
                'status' => $status,
                'priority' => $i % 5 === 0 ? 'rush' : 'normal',
                'served_at' => $status === KitchenTicketStatus::SERVED->value
                    ? ($ticketOverrides['served_at'] ?? $time)
                    : null,
                'created_at' => $time,
                'updated_at' => $time,
            ], $ticketOverrides);

            $created = $this->createOrderWithKitchenTicket(
                branch: $branch,
                cashier: $cashier,
                meal: $meal,
                orderOverrides: $mergedOrderOverrides,
                ticketOverrides: $mergedTicketOverrides
            );

            $tickets[] = $created['ticket'];
        }

        return $tickets;
    }
}
