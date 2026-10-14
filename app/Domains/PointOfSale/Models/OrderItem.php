<?php

namespace App\Domains\PointOfSale\Models;

use App\Domains\Kitchen\Models\KitchenTicketItem;
use App\Domains\Menu\Models\Meal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
        'selected_options' => 'array',
    ];

    /**
     * Parent order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Meal snapshot reference.
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class, 'meal_id');
    }

    /**
     * Associated cook preparation item on KDS.
     */
    public function kitchenTicketItem(): HasOne
    {
        return $this->hasOne(KitchenTicketItem::class, 'order_item_id');
    }
}
