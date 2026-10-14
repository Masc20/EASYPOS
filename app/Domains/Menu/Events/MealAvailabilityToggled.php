<?php

namespace App\Domains\Menu\Events;

use App\Domains\Menu\Models\Meal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MealAvailabilityToggled
{
    use Dispatchable, SerializesModels;

    public function __construct(public Meal $meal) {}
}
