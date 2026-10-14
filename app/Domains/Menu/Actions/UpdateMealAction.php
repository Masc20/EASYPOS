<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealUpdated;
use App\Domains\Menu\Models\Meal;

class UpdateMealAction
{
    public function handle(Meal|int $meal, array $data): Meal
    {
        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = trim($data['name']);
        }

        if (array_key_exists('menu_code', $data)) {
            $payload['menu_code'] = !empty($data['menu_code']) ? strtoupper(trim($data['menu_code'])) : null;
        }

        if (array_key_exists('category_id', $data)) {
            $payload['category_id'] = $data['category_id'];
        }

        if (array_key_exists('price', $data)) {
            $payload['price'] = (float) $data['price'];
        }

        if (array_key_exists('cost_price', $data)) {
            $payload['cost_price'] = !empty($data['cost_price']) ? (float) $data['cost_price'] : null;
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = !empty($data['description']) ? trim($data['description']) : null;
        }

        if (array_key_exists('is_available', $data)) {
            $payload['is_available'] = (bool) $data['is_available'];
        }

        if (array_key_exists('track_stock', $data)) {
            $payload['track_stock'] = (bool) $data['track_stock'];
            $payload['stock_quantity'] = $payload['track_stock'] ? (int) ($data['stock_quantity'] ?? 0) : null;
        }

        $instance->update($payload);

        event(new MealUpdated($instance));

        return $instance;
    }
}
