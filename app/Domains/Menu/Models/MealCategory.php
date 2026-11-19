<?php

namespace App\Domains\Menu\Models;

use App\Domains\Branches\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class MealCategory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Cache invalidation hooks for active categories.
     */
    protected static function booted(): void
    {
        $invalidate = function (MealCategory $category): void {
            Cache::forget('meal_categories_active');
            Cache::forget('meal_categories_active_all');
            Cache::forget('meal_categories_active_global');
            Cache::forget('meal_categories_active_branch_-1');

            $branchId = $category->branch_id !== null ? (int) $category->branch_id : null;
            if ($branchId === null || $category->wasChanged('branch_id')) {
                foreach (Branch::pluck('id') as $id) {
                    Cache::forget("meal_categories_active_branch_{$id}");
                }

                return;
            }

            Cache::forget("meal_categories_active_branch_{$branchId}");
        };

        static::saved($invalidate);
        static::deleted($invalidate);
    }

    /**
     * Scope categories to global plus the specified branch.
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
     * Retrieve cached active categories scoped to a branch (or all branches for HQ).
     */
    public static function cachedActiveForBranch(?int $branchId, bool $includeAllBranches = false): Collection
    {
        if ($includeAllBranches && $branchId === null) {
            return Cache::remember('meal_categories_active_all', 3600, function () {
                return static::query()
                    ->where('is_active', true)
                    ->orderBy('display_order')
                    ->get();
            });
        }

        if ($branchId === null) {
            return Cache::remember('meal_categories_active_global', 3600, function () {
                return static::query()
                    ->whereNull('branch_id')
                    ->where('is_active', true)
                    ->orderBy('display_order')
                    ->get();
            });
        }

        return Cache::remember("meal_categories_active_branch_{$branchId}", 3600, function () use ($branchId) {
            return static::query()
                ->forBranch($branchId)
                ->where('is_active', true)
                ->orderBy('display_order')
                ->get();
        });
    }

    /**
     * Meals under this category.
     */
    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class, 'category_id');
    }

    /**
     * Branch this category belongs to (null = global).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
