<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Branches\Models\Branch;
use App\Domains\Menu\Events\MealDeleted;
use App\Domains\Menu\Models\Meal;
use Illuminate\Support\Facades\Cache;

class DeleteMealAction
{
    public function handle(Meal|int $meal): bool
    {
        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        $user = auth()->user();
        if ($user && ! $user->isOwner() && ! $user->can('menu.manage')) {
            abort(403, 'Unauthorized. You do not have permission to delete menu items.');
        }

        if ($user && $user->isBranchScoped()) {
            if ($instance->branch_id === null) {
                abort(403, 'Unauthorized. Global menu dishes can only be deleted by system administrators.');
            }
            if ((int) $instance->branch_id !== (int) $user->branch_id) {
                abort(403, 'Unauthorized. You cannot delete a dish belonging to another branch.');
            }
        }

        $deleted = (bool) $instance->delete();

        $timestamp = now()->timestamp;
        Cache::forever('menu_version', $timestamp);
        if ($instance->branch_id !== null) {
            Cache::forever("branch_{$instance->branch_id}_menu_version", $timestamp);
        } else {
            Cache::forever('branch_global_menu_version', $timestamp);
            try {
                $branches = Branch::pluck('id');
                foreach ($branches as $bId) {
                    Cache::forever("branch_{$bId}_menu_version", $timestamp);
                }
            } catch (\Throwable $e) {
                // Fallback if Branch table is not seeded
            }
        }

        event(new MealDeleted($instance));

        return $deleted;
    }
}
