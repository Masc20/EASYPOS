<?php

namespace App\Domains\Kitchen\Models;

use App\Domains\PointOfSale\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicketItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * Parent kitchen ticket.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(KitchenTicket::class, 'ticket_id');
    }

    /**
     * Associated order line item with special instructions.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
