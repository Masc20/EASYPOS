<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealCreated;
use App\Domains\Menu\Models\Meal;

class CreateMealAction
{
    public function handle(array $data): Meal
    {
        $meal = Meal::create([
            'name' => trim($data['name']),
            'menu_code' => !empty($data['menu_code']) ? strtoupper(trim($data['menu_code'])) : null,
            'category_id' => $data['category_id'],
            'price' => (float) $data['price'],
            'cost_price' => !empty($data['cost_price']) ? (float) $data['cost_price'] : null,
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'is_available' => (bool) ($data['is_available'] ?? true),
            'track_stock' => (bool) ($data['track_stock'] ?? false),
            'stock_quantity' => !empty($data['track_stock']) ? (int) ($data['stock_quantity'] ?? 0) : null,
            'created_by' => $data['created_by'] ?? auth()->id(),
        ]);

        event(new MealCreated($meal));

        return $meal;
    }
}
