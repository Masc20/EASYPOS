<?php

namespace App\Domains\Kitchen\Models;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Models\Order;
use App\Support\Enums\KitchenTicketItemStatus;
use App\Support\Enums\KitchenTicketStatus;
use App\Support\Enums\OrderStatus;
use App\Support\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
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
     *
     * @return HasMany<KitchenTicketItem, $this>
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
        return $query->whereNull('served_at')->where('status', '!=', KitchenTicketStatus::CANCELLED->value);
    }

    /**
     * Scope tickets for a specific branch.
     *
     * @param  Builder<KitchenTicket>  $query
     * @return Builder<KitchenTicket>
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope tickets whose associated order has not been cancelled or refunded.
     */
    public function scopeWhereOrderNotVoided(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('order_id')->orWhereHas('order', function (Builder $oq) {
                $oq->where('status', '!=', OrderStatus::CANCELLED->value)
                    ->where('payment_status', '!=', PaymentStatus::REFUNDED->value);
            });
        });
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
        return $this->items->every(fn ($item) => $item->status === KitchenTicketItemStatus::DONE->value);
    }
}
