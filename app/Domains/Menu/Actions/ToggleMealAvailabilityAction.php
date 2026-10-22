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
        $instance->is_available = ! $instance->is_available;
        $instance->save();

        event(new MealAvailabilityToggled($instance));

        return $instance;
    }
}
