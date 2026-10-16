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

    /**
     * Scope tickets for a specific branch.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope ordering by rush priority first, then FIFO by creation time.
     */
    public function scopeRushFirst($query)
    {
        return $query->orderByRaw("CASE WHEN priority = 'rush' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'asc');
    }

    /**
     * Elapsed minutes since ticket arrived from POS.
     */
    public function getElapsedMinutesAttribute(): int
    {
        return $this->created_at ? (int) $this->created_at->diffInMinutes(now()) : 0;
    }

    /**
     * Urgency level: normal (<10m), warning (10-20m), urgent (>20m).
     */
    public function getUrgencyLevelAttribute(): string
    {
        $mins = $this->elapsed_minutes;

        if ($mins >= 20 || $this->priority === 'rush') {
            return 'urgent';
        }

        if ($mins >= 10) {
            return 'warning';
        }

        return 'normal';
    }

    /**
     * Check if all items in the ticket are completed.
     */
    public function areAllItemsDone(): bool
    {
        return $this->items->every(fn($item) => $item->status === 'done');
    }
}
