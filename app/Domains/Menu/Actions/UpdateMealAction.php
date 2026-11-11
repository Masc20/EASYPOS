<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealUpdated;
use App\Domains\Menu\Models\Meal;

class UpdateMealAction
{
    public function handle(Meal|int $meal, array $data): Meal
    {
        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        $user = auth()->user();
        if ($user && ! $user->isOwner() && ! $user->can('menu.manage')) {
            abort(403, 'Unauthorized. You do not have permission to modify menu items.');
        }

        if ($user && $user->isBranchScoped()) {
            if ($instance->branch_id === null) {
                abort(403, 'Unauthorized. Global menu dishes can only be modified by system administrators.');
            }
            if ((int) $instance->branch_id !== (int) $user->branch_id) {
                abort(403, 'Unauthorized. You cannot modify a dish belonging to another branch.');
            }
            if (array_key_exists('branch_id', $data) && $data['branch_id'] === null) {
                abort(403, 'Unauthorized. You cannot make a branch dish global.');
            }
            if (array_key_exists('branch_id', $data) && $data['branch_id'] !== null && (int) $data['branch_id'] !== (int) $user->branch_id) {
                abort(403, 'Unauthorized. You cannot transfer a dish to another branch.');
            }
        }

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
