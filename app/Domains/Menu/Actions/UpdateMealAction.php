<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\Concerns\ValidatesMealInput;
use App\Domains\Menu\Events\MealUpdated;
use App\Domains\Menu\Models\Meal;
use App\Support\InputSanitizer;

class UpdateMealAction
{
    use ValidatesMealInput;

    public function handle(Meal|int $meal, array $data): Meal
    {
        $user = auth()->user();
        if (! $user instanceof User || (! $user->isOwner() && ! $user->can('menu.manage'))) {
            abort(403, 'Unauthorized. You do not have permission to modify menu items.');
        }

        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        if ($user->isBranchScoped()) {
            $effectiveBranchId = $user->effectiveBranchId();
            if ($user->branch_id === null || $effectiveBranchId < 0) {
                abort(403, 'Unauthorized. Branch assignment required.');
            }
            if ($instance->branch_id === null) {
                abort(403, 'Unauthorized. Global menu dishes can only be modified by system administrators.');
            }
            if ((int) $instance->branch_id !== $effectiveBranchId) {
                abort(403, 'Unauthorized. You cannot modify a dish belonging to another branch.');
            }
            if (array_key_exists('branch_id', $data) && $data['branch_id'] === null) {
                abort(403, 'Unauthorized. You cannot make a branch dish global.');
            }
            if (array_key_exists('branch_id', $data) && (int) $data['branch_id'] !== $effectiveBranchId) {
                abort(403, 'Unauthorized. You cannot transfer a dish to another branch.');
            }
        }

        $payload = [];

        if (array_key_exists('category_id', $data)) {
            $targetBranchId = $instance->branch_id !== null ? (int) $instance->branch_id : null;
            $category = $this->validateCategoryForBranch($user, $data['category_id'], $targetBranchId);
            $payload['category_id'] = $category->id;
        }

        if (array_key_exists('name', $data)) {
            $payload['name'] = $this->sanitizeRequiredMealName($data['name']);
        }

        if (array_key_exists('menu_code', $data)) {
            $payload['menu_code'] = ! empty($data['menu_code']) ? strtoupper(trim($data['menu_code'])) : null;
        }

        if (array_key_exists('price', $data)) {
            $payload['price'] = (float) $data['price'];
        }

        if (array_key_exists('cost_price', $data)) {
            $payload['cost_price'] = ! empty($data['cost_price']) ? (float) $data['cost_price'] : null;
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = InputSanitizer::clean($data['description'] !== null ? (string) $data['description'] : null);
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
