<?php

namespace App\Domains\Menu\Models;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meal extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_available' => 'boolean',
        'track_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'kitchen_tags' => 'array',
    ];

    /**
     * Determine if the meal is sold-out (86'd).
     */
    public function isSoldOut(): bool
    {
        return ! $this->is_available || ($this->track_stock && $this->stock_quantity <= 0);
    }

    /**
     * Scope query to available (not sold out) dishes.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true)
            ->where(function (Builder $q) {
                $q->where('track_stock', false)
                    ->orWhere('stock_quantity', '>', 0);
            });
    }

    /**
     * Scope query to sold out (86'd) dishes.
     */
    public function scopeSoldOut(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_available', false)
                ->orWhere(function (Builder $sub) {
                    $sub->where('track_stock', true)
                        ->where('stock_quantity', '<=', 0);
                });
        });
    }

    /**
     * Scope query to branch dishes (including global dishes).
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->where(function (Builder $q) use ($branchId) {
            $q->whereNull('branch_id');
            if ($branchId !== null) {
                $q->orWhere('branch_id', $branchId);
            }
        });
    }

    /**
     * Category this meal belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MealCategory::class, 'category_id');
    }

    /**
     * Branch this meal belongs to (null = global menu).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * User (Chef or Owner) who created the meal.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
