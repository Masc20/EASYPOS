<?php

namespace App\Domains\Menu\Models;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Support\Services\CodeGeneratorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Meal extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $attributes = [
        'kitchen_tags' => '[]',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_available' => 'boolean',
        'track_stock' => 'boolean',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'kitchen_tags' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Meal $meal) {
            if (blank($meal->menu_code) && $meal->category_id) {
                $meal->menu_code = CodeGeneratorService::generateMenuCode((int) $meal->category_id);
            }
        });

        static::saved(function (Meal $meal) {
            $branchId = $meal->branch_id !== null ? (int) $meal->branch_id : null;
            static::bumpMenuVersionForBranch($branchId);

            if ($meal->wasChanged('branch_id')) {
                $originalBranchId = $meal->getOriginal('branch_id') !== null
                    ? (int) $meal->getOriginal('branch_id')
                    : null;

                if ($originalBranchId !== $branchId) {
                    static::bumpMenuVersionForBranch($originalBranchId);
                }
            }
        });

        static::deleted(function (Meal $meal) {
            $branchId = $meal->branch_id !== null ? (int) $meal->branch_id : null;
            static::bumpMenuVersionForBranch($branchId);
        });

        static::restored(function (Meal $meal) {
            $branchId = $meal->branch_id !== null ? (int) $meal->branch_id : null;
            static::bumpMenuVersionForBranch($branchId);
        });
    }

    /**
     * Monotonically bump a menu version cache key.
     */
    public static function bumpVersionKey(string $key): int
    {
        $next = max(((int) Cache::get($key, 0)) + 1, now()->timestamp);
        Cache::forever($key, $next);

        return $next;
    }

    /**
     * Bump menu version cache keys and flush catalog caches for a single branch.
     */
    public static function bumpMenuVersionForSingleBranch(int $branchId): void
    {
        static::bumpVersionKey('menu_version');
        static::bumpVersionKey("branch_{$branchId}_menu_version");
        Cache::forget('pos_menu_catalog_all');
        Cache::forget("pos_menu_catalog_{$branchId}");
    }

    /**
     * Bump menu version cache keys and flush catalog caches for the affected branch (or all branches if global).
     */
    public static function bumpMenuVersionForBranch(?int $branchId): void
    {
        if ($branchId !== null) {
            static::bumpMenuVersionForSingleBranch($branchId);

            return;
        }

        static::bumpVersionKey('menu_version');
        static::bumpVersionKey('branch_global_menu_version');
        Cache::forget('pos_menu_catalog_all');

        foreach (Branch::pluck('id') as $id) {
            static::bumpVersionKey("branch_{$id}_menu_version");
            Cache::forget("pos_menu_catalog_{$id}");
        }
    }

    /**
     * Determine if the meal is sold-out (86'd).
     */
    public function isSoldOut(): bool
    {
        return ! $this->is_available || ($this->track_stock && $this->stock_quantity <= 0);
    }

    /**
     * Resolve the branch availability override record if applicable.
     */
    public function branchOverrideFor(?int $branchId): ?MealBranchAvailability
    {
        if ($this->branch_id !== null || $branchId === null) {
            return null;
        }

        return $this->relationLoaded('branchAvailabilities')
            ? $this->branchAvailabilities->firstWhere('branch_id', $branchId)
            : $this->branchAvailabilities()->where('branch_id', $branchId)->first();
    }

    /**
     * Determine effective availability for a given branch.
     * For global/shared dishes (branch_id === null), checks branch-level 86'd overrides first.
     */
    public function isAvailableForBranch(?int $branchId): bool
    {
        $override = $this->branchOverrideFor($branchId);
        if ($override !== null) {
            return (bool) $override->is_available;
        }

        return (bool) $this->is_available;
    }

    /**
     * Determine whether portion tracking is enabled for a given branch.
     */
    public function trackStockForBranch(?int $branchId): bool
    {
        $override = $this->branchOverrideFor($branchId);
        if ($override !== null && $override->track_stock !== null) {
            return (bool) $override->track_stock;
        }

        return (bool) $this->track_stock;
    }

    /**
     * Determine effective remaining portion count for a given branch.
     */
    public function stockQuantityForBranch(?int $branchId): ?int
    {
        $override = $this->branchOverrideFor($branchId);
        if ($override !== null && $override->track_stock !== null) {
            return $override->track_stock ? (int) ($override->stock_quantity ?? 0) : null;
        }

        return $this->track_stock ? (int) ($this->stock_quantity ?? 0) : null;
    }

    /**
     * Determine if the meal is sold-out (86'd) for a given branch.
     */
    public function isSoldOutForBranch(?int $branchId): bool
    {
        if (! $this->isAvailableForBranch($branchId)) {
            return true;
        }

        return $this->trackStockForBranch($branchId) && (($this->stockQuantityForBranch($branchId) ?? 0) <= 0);
    }

    /**
     * Scope query to dishes effectively matching the target availability state for the given branch.
     */
    protected function scopeWithBranchAvailability(Builder $query, ?int $branchId, bool $available): Builder
    {
        if ($branchId === null) {
            return $query->where('is_available', $available);
        }

        return $query->where(function (Builder $q) use ($branchId, $available) {
            $q->where(function (Builder $branchMeal) use ($available) {
                $branchMeal->whereNotNull('meals.branch_id')
                    ->where('meals.is_available', $available);
            })->orWhere(function (Builder $globalMeal) use ($branchId, $available) {
                $globalMeal->whereNull('meals.branch_id')
                    ->where(function (Builder $effective) use ($branchId, $available) {
                        $effective->whereHas('branchAvailabilities', function (Builder $ov) use ($branchId, $available) {
                            $ov->where('branch_id', $branchId)->where('is_available', $available);
                        })->orWhere(function (Builder $noOv) use ($branchId, $available) {
                            $noOv->where('meals.is_available', $available)
                                ->whereDoesntHave('branchAvailabilities', fn (Builder $ov) => $ov->where('branch_id', $branchId));
                        });
                    });
            });
        });
    }

    /**
     * Scope query to dishes effectively marked In Stock (is_available = true) for the given branch.
     */
    public function scopeInStockForBranch(Builder $query, ?int $branchId): Builder
    {
        return $this->scopeWithBranchAvailability($query, $branchId, true);
    }

    /**
     * Scope query to dishes effectively marked 86'd / Sold Out (is_available = false) for the given branch.
     */
    public function scopeEightSixForBranch(Builder $query, ?int $branchId): Builder
    {
        return $this->scopeWithBranchAvailability($query, $branchId, false);
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

    /**
     * Per-branch availability overrides for global/shared meals.
     *
     * @return HasMany<MealBranchAvailability, $this>
     */
    public function branchAvailabilities(): HasMany
    {
        return $this->hasMany(MealBranchAvailability::class, 'meal_id');
    }
}
