<?php

namespace App\Domains\Menu\Models;

use App\Domains\Branches\Models\Branch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealBranchAvailability extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'meal_id' => 'integer',
        'branch_id' => 'integer',
        'is_available' => 'boolean',
        'track_stock' => 'boolean',
        'stock_quantity' => 'integer',
    ];

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class, 'meal_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
