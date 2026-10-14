<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use App\Domains\Menu\Models\Meal;
use Livewire\Attributes\On;
use Livewire\Component;

class CheckoutCart extends Component
{
    /**
     * @var array<int, array{
     *     meal_id: int,
     *     name: string,
     *     menu_code: ?string,
     *     price: float,
     *     quantity: int,
     *     special_instructions: ?string,
     *     max_quantity?: ?int
     * }>
     */
    public array $items = [];

    public string $orderType = 'dine_in'; // dine_in, takeout, delivery
    public string $tableNumber = '';
    public string $customerName = '';
    public string $discountType = 'none'; // none, senior_pwd, custom
    public float $customDiscount = 0.00;
    public string $notes = '';

    #[On('add-to-cart')]
    public function addItem(int $mealId): void
    {
        $meal = Meal::select(['id', 'name', 'menu_code', 'price', 'is_available', 'track_stock', 'stock_quantity'])
            ->find($mealId);
        if (!$meal || !$meal->is_available) {
            return;
        }

        $maxQty = $meal->track_stock ? $meal->stock_quantity : null;

        // Check if item already exists in cart
        foreach ($this->items as $index => $item) {
            if ($item['meal_id'] === $mealId) {
                if ($maxQty !== null && $item['quantity'] >= $maxQty) {
                    $this->dispatch('pos-alert', [
                        'type' => 'warning',
                        'message' => "Maximum daily portion limit reached for {$meal->name} ({$maxQty} available).",
                    ]);
                    return;
                }
                $this->items[$index]['quantity']++;
                return;
            }
        }

        // Otherwise add new line item
        $this->items[] = [
            'meal_id' => $meal->id,
            'name' => $meal->name,
            'menu_code' => $meal->menu_code,
            'price' => (float) $meal->price,
            'quantity' => 1,
            'special_instructions' => '',
            'max_quantity' => $maxQty,
        ];
    }

    public function incrementQuantity(int $index): void
    {
        if (!isset($this->items[$index])) {
            return;
        }

        $item = &$this->items[$index];
        if ($item['max_quantity'] !== null && $item['quantity'] >= $item['max_quantity']) {
            $this->dispatch('pos-alert', [
                'type' => 'warning',
                'message' => "Cannot exceed {$item['max_quantity']} portions.",
            ]);
            return;
        }

        $item['quantity']++;
    }

    public function decrementQuantity(int $index): void
    {
        if (!isset($this->items[$index])) {
            return;
        }

        if ($this->items[$index]['quantity'] > 1) {
            $this->items[$index]['quantity']--;
        } else {
            $this->removeItem($index);
        }
    }

    public function removeItem(int $index): void
    {
        if (isset($this->items[$index])) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function clearCart(): void
    {
        $this->items = [];
        $this->tableNumber = '';
        $this->customerName = '';
        $this->discountType = 'none';
        $this->customDiscount = 0.00;
        $this->notes = '';
    }

    #[On('cart-reset')]
    #[On('order-completed')]
    public function handleCartReset(): void
    {
        $this->clearCart();
    }

    public function getSubtotalProperty(): float
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += ($item['price'] * $item['quantity']);
        }
        return round($subtotal, 2);
    }

    public function getDiscountAmountProperty(): float
    {
        if ($this->discountType === 'senior_pwd') {
            return round($this->subtotal * 0.20, 2); // 20% RA 9994 / RA 10754 Senior & PWD Discount
        }

        if ($this->discountType === 'custom') {
            return min($this->subtotal, max(0.00, round($this->customDiscount, 2)));
        }

        return 0.00;
    }

    public function getTotalAmountProperty(): float
    {
        return max(0.00, round($this->subtotal - $this->discountAmount, 2));
    }

    public function proceedToCheckout(): void
    {
        if (empty($this->items)) {
            $this->dispatch('pos-alert', [
                'type' => 'error',
                'message' => 'Your cart is empty. Tap dishes to ring up an order.',
            ]);
            return;
        }

        // Open checkout modal with payload
        $this->dispatch('open-checkout', [
            'items' => $this->items,
            'order_type' => $this->orderType,
            'table_number' => $this->tableNumber,
            'customer_name' => $this->customerName,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discountAmount,
            'total_amount' => $this->totalAmount,
            'notes' => $this->notes,
        ]);
    }

    public function render()
    {
        return view('livewire.point-of-sale.checkout.checkout-cart', [
            'subtotal' => $this->subtotal,
            'discountAmount' => $this->discountAmount,
            'totalAmount' => $this->totalAmount,
            'itemCount' => array_sum(array_column($this->items, 'quantity')),
        ]);
    }
}
