<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;

class ToggleMealAvailabilityAction
{
    public function handle(Meal|int $meal): Meal
    {
        if (auth()->check() && ! auth()->user()->can('menu.manage')) {
            abort(403, 'Unauthorized. Only Kitchen and Managers can toggle dish availability.');
        }

        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        $user = auth()->user();
        if ($user && ! $user->isOwner() && $user->branch_id && $instance->branch_id !== null && (int) $instance->branch_id !== (int) $user->branch_id) {
            abort(403, 'Unauthorized. You cannot toggle availability for a dish belonging to another branch.');
        }

        $instance->is_available = ! $instance->is_available;
        $instance->save();

        event(new MealAvailabilityToggled($instance));

        return $instance;
    }
}
