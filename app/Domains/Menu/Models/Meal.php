<?php

namespace App\Domains\Menu\Models;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
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
