<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\Concerns\ValidatesMealInput;
use App\Domains\Menu\Events\MealCreated;
use App\Domains\Menu\Models\Meal;
use App\Support\InputSanitizer;

class CreateMealAction
{
    use ValidatesMealInput;

    public function handle(array $data): Meal
    {
        $user = auth()->user();
        if (! $user instanceof User || (! $user->isOwner() && ! $user->can('menu.manage'))) {
            abort(403, 'Unauthorized. You do not have permission to create menu items.');
        }

        if ($user->isBranchScoped()) {
            $effectiveBranchId = $user->effectiveBranchId();
            if ($user->branch_id === null || $effectiveBranchId < 0) {
                abort(403, 'Unauthorized. Branch assignment required.');
            }
            if (array_key_exists('branch_id', $data) && $data['branch_id'] === null) {
                abort(403, 'Unauthorized. Global menu dishes can only be created by system administrators.');
            }
            if (array_key_exists('branch_id', $data) && (int) $data['branch_id'] !== $effectiveBranchId) {
                abort(403, 'Unauthorized. You cannot create dishes for another branch.');
            }
            $branchId = $effectiveBranchId;
        } else {
            $branchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $user->branch_id;
        }

        $targetBranchId = filled($branchId) ? (int) $branchId : null;
        $category = $this->validateCategoryForBranch($user, $data['category_id'] ?? null, $targetBranchId);
        $sanitizedName = $this->sanitizeRequiredMealName($data['name'] ?? null);

        $meal = Meal::create([
            'name' => $sanitizedName,
            'menu_code' => ! empty($data['menu_code']) ? strtoupper(trim($data['menu_code'])) : null,
            'category_id' => $category->id,
            'branch_id' => $targetBranchId,
            'price' => (float) $data['price'],
            'cost_price' => ! empty($data['cost_price']) ? (float) $data['cost_price'] : null,
            'description' => array_key_exists('description', $data)
                ? InputSanitizer::clean($data['description'] !== null ? (string) $data['description'] : null)
                : null,
            'is_available' => (bool) ($data['is_available'] ?? true),
            'track_stock' => (bool) ($data['track_stock'] ?? false),
            'stock_quantity' => $this->validatedPortionQuantity($data),
            'created_by' => $data['created_by'] ?? $user->id,
        ]);

        event(new MealCreated($meal));

        return $meal;
    }
}
