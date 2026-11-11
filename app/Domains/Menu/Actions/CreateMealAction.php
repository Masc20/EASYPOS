<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealCreated;
use App\Domains\Menu\Models\Meal;

class CreateMealAction
{
    public function handle(array $data): Meal
    {
        $user = auth()->user();
        if ($user && ! $user->isOwner() && ! $user->can('menu.manage')) {
            abort(403, 'Unauthorized. You do not have permission to create menu items.');
        }

        if ($user && $user->isBranchScoped()) {
            if (array_key_exists('branch_id', $data) && $data['branch_id'] === null) {
                abort(403, 'Unauthorized. Global menu dishes can only be created by system administrators.');
            }
            if (array_key_exists('branch_id', $data) && $data['branch_id'] !== null && (int) $data['branch_id'] !== (int) $user->branch_id) {
                abort(403, 'Unauthorized. You cannot create dishes for another branch.');
            }
            $branchId = (int) $user->branch_id;
        } else {
            $branchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $user?->branch_id;
        }

        $meal = Meal::create([
            'name' => trim($data['name']),
            'menu_code' => !empty($data['menu_code']) ? strtoupper(trim($data['menu_code'])) : null,
            'category_id' => $data['category_id'],
            'branch_id' => $branchId,
            'price' => (float) $data['price'],
            'cost_price' => !empty($data['cost_price']) ? (float) $data['cost_price'] : null,
            'description' => !empty($data['description']) ? trim($data['description']) : null,
            'is_available' => (bool) ($data['is_available'] ?? true),
            'track_stock' => (bool) ($data['track_stock'] ?? false),
            'stock_quantity' => !empty($data['track_stock']) ? (int) ($data['stock_quantity'] ?? 0) : null,
            'created_by' => $data['created_by'] ?? $user?->id,
        ]);

        event(new MealCreated($meal));

        return $meal;
    }
}
