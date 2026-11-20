<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealDeleted;
use App\Domains\Menu\Models\Meal;

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
            if ($instance->branch_id === null) {
                abort(403, 'Unauthorized. Global menu dishes can only be deleted by system administrators.');
            }
            if ((int) $instance->branch_id !== $effectiveBranchId) {
                abort(403, 'Unauthorized. You cannot delete a dish belonging to another branch.');
            }
        }

        $deleted = (bool) $instance->delete();

        event(new MealDeleted($instance));

        return $deleted;
    }
}
