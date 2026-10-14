<?php

namespace App\Domains\PointOfSale\Models;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    /**
     * Branch where order was rung up.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Cashier who took the order.
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Line items in this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Payments collected for this order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    /**
     * Linked kitchen ticket sent to cook station.
     */
    public function kitchenTicket(): HasOne
    {
        return $this->hasOne(KitchenTicket::class, 'order_id');
    }

    /**
     * Scope for orders belonging to a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope for today's orders.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Human-readable label for order type.
     */
    public function getOrderTypeLabelAttribute(): string
    {
        return match ($this->order_type) {
            'dine_in' => 'Dine In',
            'takeout' => 'Takeout',
            default => ucfirst($this->order_type),
        };
    }

    /**
     * Compute VATable Sales (net of 12% VAT).
     */
    public function getVatableSalesAttribute(): float
    {
        if ($this->discount_amount > 0) {
            return 0.00;
        }

        return round((float) $this->total_amount / 1.12, 2);
    }

    /**
     * Compute 12% VAT amount.
     */
    public function getVatAmountAttribute(): float
    {
        if ($this->discount_amount > 0) {
            return 0.00;
        }

        return round($this->vatable_sales * 0.12, 2);
    }

    /**
     * Compute VAT-Exempt Sales (Senior / PWD).
     */
    public function getVatExemptSalesAttribute(): float
    {
        if ($this->discount_amount > 0) {
            return (float) $this->total_amount;
        }

        return 0.00;
    }

    /**
     * Zero-Rated Sales.
     */
    public function getZeroRatedSalesAttribute(): float
    {
        return 0.00;
    }
}
