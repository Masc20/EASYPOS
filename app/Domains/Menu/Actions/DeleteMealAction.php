<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealDeleted;
use App\Domains\Menu\Models\Meal;

class DeleteMealAction
{
    public function handle(Meal|int $meal): bool
    {
        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        $user = auth()->user();
        if ($user && ! $user->isOwner() && $user->branch_id && $instance->branch_id !== null && (int) $instance->branch_id !== (int) $user->branch_id) {
            abort(403, 'Unauthorized. You cannot delete a dish belonging to another branch.');
        }

        $deleted = (bool) $instance->delete();

        event(new MealDeleted($instance));

        return $deleted;
    }
}
