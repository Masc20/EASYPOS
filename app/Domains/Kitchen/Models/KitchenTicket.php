<?php

namespace App\Domains\Kitchen\Models;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenTicket extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
    ];

    /**
     * Associated customer order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Branch kitchen this ticket is for.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Cook currently preparing this ticket.
     */
    public function cook(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cook_id');
    }

    /**
     * Ticket item breakdown.
     */
    public function items(): HasMany
    {
        return $this->hasMany(KitchenTicketItem::class, 'ticket_id');
    }

    /**
     * Scope for live active tickets on kitchen queue.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('served_at')->where('status', '!=', 'cancelled');
    }
}
