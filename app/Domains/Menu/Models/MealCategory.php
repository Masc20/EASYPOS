<?php

namespace App\Domains\Menu\Models;

use App\Domains\Branches\Models\Branch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('meal_categories_active');
        });

        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('meal_categories_active');
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
