<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Branches\Models\Branch;
use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealBranchAvailability;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ToggleMealAvailabilityAction
{
    public function handle(Meal|int $meal): Meal
    {
        $user = auth()->user();
        if ($user && ! $user->isOwner() && ! $user->can('menu.manage')) {
            abort(403, 'Unauthorized. Only Kitchen and Managers can toggle dish availability.');
        }

        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        if ($user && $user->isBranchScoped() && $instance->branch_id !== null && (int) $instance->branch_id !== (int) $user->branch_id) {
            abort(403, 'Unauthorized. You cannot toggle availability for a dish belonging to another branch.');
        }

        $isBranchScopedGlobalToggle = $instance->branch_id === null && $user && $user->isBranchScoped();
        $scopedBranchId = $isBranchScopedGlobalToggle ? (int) $user->branch_id : null;
        $effectiveAvailability = null;

        DB::transaction(function () use ($instance, $isBranchScopedGlobalToggle, $scopedBranchId, &$effectiveAvailability): void {
            if ($isBranchScopedGlobalToggle && $scopedBranchId !== null) {
                $effectiveAvailability = ! $instance->isAvailableForBranch($scopedBranchId);

                MealBranchAvailability::updateOrCreate(
                    [
                        'meal_id' => $instance->id,
                        'branch_id' => $scopedBranchId,
                    ],
                    [
                        'is_available' => $effectiveAvailability,
                    ]
                );

                // Refresh relation if loaded so callers inspecting the returned Meal get accurate branch availability
                $instance->unsetRelation('branchAvailabilities');

                return;
            }

            $instance->is_available = ! $instance->is_available;
            $instance->save();
            $effectiveAvailability = (bool) $instance->is_available;
        });

        $timestamp = now()->timestamp;
        Cache::forever('menu_version', $timestamp);

        if ($isBranchScopedGlobalToggle && $scopedBranchId !== null) {
            Cache::forever("branch_{$scopedBranchId}_menu_version", $timestamp);
        } elseif ($instance->branch_id !== null) {
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

        try {
            event(new MealAvailabilityToggled($instance, $scopedBranchId, $effectiveAvailability));
        } catch (\Throwable $e) {
            report($e);
        }

        return $instance;
    }
}
