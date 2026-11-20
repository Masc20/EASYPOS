<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Events\MealDeleted;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealBranchAvailability;

class DeleteMealAction
{
    public function handle(Meal|int $meal): bool
    {
        $user = auth()->user();
        if (! $user || ! $user->can('menu.manage')) {
            abort(403, 'Unauthorized. You do not have permission to delete menu items.');
        }

        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        if ($user->isBranchScoped()) {
            $effectiveBranchId = $user->effectiveBranchId();
            if ($user->branch_id === null || $effectiveBranchId < 0) {
                abort(403, 'Unauthorized. Branch assignment required.');
            }
            if ($instance->branch_id !== null && (int) $instance->branch_id !== $effectiveBranchId) {
                abort(403, 'Unauthorized. You cannot delete a dish belonging to another branch.');
            }
        }

        if ($user->isBranchScoped()) {
            MealBranchAvailability::upsert([
                'meal_id' => $instance->id,
                'branch_id' => $effectiveBranchId,
                'is_hidden' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['meal_id', 'branch_id'], ['is_hidden', 'updated_at']);
            $instance->unsetRelation('branchAvailabilities');
            Meal::bumpMenuVersionForSingleBranch($effectiveBranchId);
            event(new MealAvailabilityToggled($instance, $effectiveBranchId, false));

            return true;
        }

        $deleted = (bool) $instance->delete();

        event(new MealDeleted($instance));

        return $deleted;
    }
}
