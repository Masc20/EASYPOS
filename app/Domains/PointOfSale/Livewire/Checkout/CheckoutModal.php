<?php

namespace App\Domains\PointOfSale\Livewire\Checkout;

use App\Domains\PointOfSale\Actions\CreateOrderAction;
use App\Domains\PointOfSale\Actions\ProcessPaymentAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class CheckoutModal extends Component
{
    public bool $isOpen = false;

    public array $items = [];
    public string $orderType = 'dine_in';
    public string $tableNumber = '';
    public string $customerName = '';
    public float $subtotal = 0.00;
    public float $discountAmount = 0.00;
    public float $totalAmount = 0.00;
    public string $notes = '';

    public string $paymentMethod = 'cash'; // cash, credit_card, qr_transfer
    public string $amountTendered = '';
    public string $referenceNumber = '';

    public ?string $errorMessage = null;

    #[On('open-checkout')]
    public function openWithData(
        mixed $items = [],
        string $order_type = 'dine_in',
        string $table_number = '',
        string $customer_name = '',
        float|int $subtotal = 0,
        float|int $discount_amount = 0,
        float|int $total_amount = 0,
        string $notes = '',
        ?array $payload = null
    ): void {
        $this->resetErrorBag();
        $this->errorMessage = null;

        // If entire payload was passed as a single array (PHPUnit or wrapper)
        if (is_array($items) && isset($items['items'])) {
            $payload = $items;
            $items = $payload['items'];
        }

        if ($payload) {
            $items = $payload['items'] ?? $items;
            $order_type = $payload['order_type'] ?? $order_type;
            $table_number = $payload['table_number'] ?? $table_number;
            $customer_name = $payload['customer_name'] ?? $customer_name;
            $subtotal = $payload['subtotal'] ?? $subtotal;
            $discount_amount = $payload['discount_amount'] ?? $discount_amount;
            $total_amount = $payload['total_amount'] ?? $total_amount;
            $notes = $payload['notes'] ?? $notes;
        }

        $this->items = is_array($items) ? $items : [];
        $this->orderType = $order_type;
        $this->tableNumber = $table_number;
        $this->customerName = $customer_name;
        $this->subtotal = (float) $subtotal;
        $this->discountAmount = (float) $discount_amount;
        $this->totalAmount = (float) $total_amount;
        $this->notes = $notes;

        $this->paymentMethod = 'cash';
        $this->amountTendered = number_format($this->totalAmount, 2, '.', '');
        $this->referenceNumber = '';

        $this->isOpen = true;
        $this->dispatch('open-modal', 'checkout-modal');
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->errorMessage = null;
        $this->dispatch('close-modal', 'checkout-modal');
    }

    public function setExactTender(): void
    {
        $this->amountTendered = number_format($this->totalAmount, 2, '.', '');
    }

    public function setTenderAmount(float $amount): void
    {
        $this->amountTendered = number_format($amount, 2, '.', '');
    }

    public function addTenderAmount(float $increment): void
    {
        $current = (float) $this->amountTendered;
        $this->amountTendered = number_format($current + $increment, 2, '.', '');
    }

    public function getChangeDueProperty(): float
    {
        $tendered = (float) $this->amountTendered;
        if ($tendered < $this->totalAmount) {
            return 0.00;
        }
        return round($tendered - $this->totalAmount, 2);
    }

    public function processCheckoutFromClient(
        array $data,
        CreateOrderAction $createOrderAction,
        ProcessPaymentAction $processPaymentAction
    ): ?string {
        $this->items = $data['items'] ?? [];
        $this->orderType = $data['order_type'] ?? 'dine_in';
        $this->tableNumber = $data['table_number'] ?? '';
        $this->customerName = $data['customer_name'] ?? '';
        $this->discountAmount = (float) ($data['discount_amount'] ?? 0);
        $this->notes = $data['notes'] ?? '';
        $this->paymentMethod = $data['payment_method'] ?? 'cash';
        $this->amountTendered = (string) ($data['amount_tendered'] ?? '');
        $this->referenceNumber = $data['reference_number'] ?? '';

        $this->processCheckout($createOrderAction, $processPaymentAction);

        return $this->errorMessage;
    }

    public function processCheckout(
        CreateOrderAction $createOrderAction,
        ProcessPaymentAction $processPaymentAction
    ): void {
        $this->errorMessage = null;

        $user = Auth::user();
        if (!$user) {
            $this->errorMessage = 'User not authenticated.';
            return;
        }

        $branchId = $user->branch_id ?? 1;

        // Validation
        if (empty($this->items)) {
            $this->errorMessage = 'Cannot checkout an empty order.';
            return;
        }

        $tenderedFloat = (float) $this->amountTendered;
        if ($this->paymentMethod === 'cash') {
            if ($tenderedFloat < $this->totalAmount) {
                $this->errorMessage = sprintf(
                    'Amount tendered (₱%.2f) is less than the total due (₱%.2f).',
                    $tenderedFloat,
                    $this->totalAmount
                );
                return;
            }
        } elseif (in_array($this->paymentMethod, ['credit_card', 'qr_transfer'])) {
            if (empty(trim($this->referenceNumber))) {
                $this->errorMessage = 'Reference / Approval Code is required for card and digital payments.';
                return;
            }
        }

        try {
            // 1. Create Order and Items
            $order = $createOrderAction->execute([
                'branch_id' => $branchId,
                'cashier_id' => $user->id,
                'order_type' => $this->orderType,
                'table_number' => $this->tableNumber,
                'customer_name' => $this->customerName,
                'discount_amount' => $this->discountAmount,
                'notes' => $this->notes,
                'items' => array_map(function ($item) {
                    return [
                        'meal_id' => $item['meal_id'],
                        'quantity' => $item['quantity'],
                        'special_instructions' => $item['special_instructions'] ?? null,
                    ];
                }, $this->items),
            ]);

            // 2. Process Payment & Dispatch to Kitchen
            $result = $processPaymentAction->execute($order, [
                'cashier_id' => $user->id,
                'payment_method' => $this->paymentMethod,
                'amount_tendered' => $tenderedFloat,
                'reference_number' => $this->referenceNumber,
            ]);

            $this->isOpen = false;

            // Close checkout modal
            $this->dispatch('close-modal', 'checkout-modal');

            // Notify Cart to reset
            $this->dispatch('cart-reset');

            // Open receipt modal and dispatch receipt event with created Order ID
            $this->dispatch('open-modal', 'receipt-modal');
            $this->dispatch('show-receipt', orderId: $order->id);

        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.point-of-sale.checkout.checkout-modal', [
            'changeDue' => $this->changeDue,
        ]);
    }
}
