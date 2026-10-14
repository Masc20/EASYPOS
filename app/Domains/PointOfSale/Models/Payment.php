<?php

namespace App\Domains\PointOfSale\Models;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_given' => 'decimal:2',
    ];

    /**
     * Order this payment settled.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Branch where transaction took place.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Cashier who received the payment.
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Formatted label for payment method.
     */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'qr_transfer' => 'QR / E-Wallet',
            'voucher' => 'Voucher',
            default => ucfirst(str_replace('_', ' ', $this->payment_method)),
        };
    }
}
