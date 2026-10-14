<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;

class ToggleMealAvailabilityAction
{
    public function handle(Meal|int $meal): Meal
    {
        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);
        $instance->is_available = ! $instance->is_available;
        $instance->save();

        event(new MealAvailabilityToggled($instance));

        return $instance;
    }
}
