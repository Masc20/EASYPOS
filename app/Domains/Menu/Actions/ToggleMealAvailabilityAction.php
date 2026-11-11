<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Identity\Models\Branch;
use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;
use Illuminate\Support\Facades\Cache;

class ToggleMealAvailabilityAction
{
    public function handle(Meal|int $meal): Meal
    {
        $user = auth()->user();
        if ($user && ! $user->isOwner() && ! $user->can('menu.manage')) {
            abort(403, 'Unauthorized. Only Kitchen and Managers can toggle dish availability.');
        }

        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        if ($user && ! $user->isOwner() && $user->branch_id && $instance->branch_id !== null && (int) $instance->branch_id !== (int) $user->branch_id) {
            abort(403, 'Unauthorized. You cannot toggle availability for a dish belonging to another branch.');
        }

        $instance->is_available = ! $instance->is_available;
        $instance->save();

        $timestamp = now()->timestamp;
        if ($instance->branch_id !== null) {
            Cache::forever("branch_{$instance->branch_id}_menu_version", $timestamp);
        } else {
            Cache::forever('branch_global_menu_version', $timestamp);
            if ($user?->branch_id) {
                Cache::forever("branch_{$user->branch_id}_menu_version", $timestamp);
            }
            try {
                $branches = Branch::pluck('id');
                foreach ($branches as $branchId) {
                    Cache::forever("branch_{$branchId}_menu_version", $timestamp);
                }
            } catch (\Throwable $e) {
                // Fallback if Branch table is not seeded
            }
        }

        event(new MealAvailabilityToggled($instance));

        return $instance;
    }
}
