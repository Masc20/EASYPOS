<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealDeleted;
use App\Domains\Menu\Models\Meal;

class DeleteMealAction
{
    public function handle(Meal|int $meal): bool
    {
        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);
        $deleted = (bool) $instance->delete();

        event(new MealDeleted($instance));

        return $deleted;
    }
}
