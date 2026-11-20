<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealBranchAvailability;
use Illuminate\Support\Facades\DB;

class ToggleMealAvailabilityAction
{
    public function handle(Meal|int $meal): Meal
    {
        $user = auth()->user();
        if (! $user || ! $user->can('menu.manage')) {
            abort(403, 'Unauthorized. Only Kitchen and Managers can toggle dish availability.');
        }

        $isBranchScoped = $user->isBranchScoped();
        $effectiveBranchId = $isBranchScoped ? $user->effectiveBranchId() : null;
        if ($isBranchScoped && ($user->branch_id === null || $effectiveBranchId < 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        $instance = $meal instanceof Meal ? $meal : Meal::findOrFail($meal);

        if ($isBranchScoped && $instance->branch_id !== null && (int) $instance->branch_id !== $effectiveBranchId) {
            abort(403, 'Unauthorized. You cannot toggle availability for a dish belonging to another branch.');
        }

        $isBranchScopedGlobalToggle = $instance->branch_id === null && $isBranchScoped;
        $scopedBranchId = $isBranchScopedGlobalToggle ? $effectiveBranchId : null;
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

        if ($isBranchScopedGlobalToggle && $scopedBranchId !== null) {
            Meal::bumpMenuVersionForSingleBranch($scopedBranchId);
        }

        try {
            event(new MealAvailabilityToggled($instance, $scopedBranchId, $effectiveAvailability));
        } catch (\Throwable $e) {
            report($e);
        }

        return $instance;
    }
}
