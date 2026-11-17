<?php

namespace App\Domains\Menu\Actions;

use App\Domains\Menu\Events\MealAvailabilityToggled;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealBranchAvailability;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateBranchPortionsAction
{
    /**
     * Update daily portion tracking & quantity for a meal, scoped strictly to the actor's branch.
     */
    public function handle(int $mealId, bool $trackStock, ?int $stockQuantity): Meal
    {
        $user = auth()->user();
        if ($user && ! $user->can('menu.manage') && ! $user->isOwner()) {
            abort(403, 'Unauthorized.');
        }

        $meal = Meal::findOrFail($mealId);
        $isBranchScoped = (bool) ($user && $user->isBranchScoped());
        $branchId = $isBranchScoped ? (int) $user->branch_id : null;

        if ($isBranchScoped && $meal->branch_id !== null && (int) $meal->branch_id !== $branchId) {
            abort(403, 'Unauthorized. You cannot modify portions for a dish belonging to another branch.');
        }

        $normalizedQty = $trackStock ? max(0, (int) ($stockQuantity ?? 0)) : null;

        $effectiveAvailable = DB::transaction(function () use ($meal, $isBranchScoped, $branchId, $trackStock, $normalizedQty): bool {
            if ($meal->branch_id === null && $isBranchScoped && $branchId !== null) {
                $existing = MealBranchAvailability::query()
                    ->where('meal_id', $meal->id)
                    ->where('branch_id', $branchId)
                    ->first();

                $currentAvailable = $existing ? (bool) $existing->is_available : (bool) $meal->is_available;
                $nextAvailable = $trackStock ? ($normalizedQty > 0) : $currentAvailable;

                MealBranchAvailability::updateOrCreate(
                    [
                        'meal_id' => $meal->id,
                        'branch_id' => $branchId,
                    ],
                    [
                        'is_available' => $nextAvailable,
                        'track_stock' => $trackStock,
                        'stock_quantity' => $normalizedQty,
                    ]
                );

                return $nextAvailable;
            }

            $nextAvailable = $trackStock ? ($normalizedQty > 0) : (bool) $meal->is_available;
            $meal->update([
                'track_stock' => $trackStock,
                'stock_quantity' => $normalizedQty,
                'is_available' => $nextAvailable,
            ]);

            return $nextAvailable;
        });

        $this->flushMenuCatalogCaches($meal->branch_id ?? $branchId);

        try {
            broadcast(new MealAvailabilityToggled($meal, $effectiveAvailable, $branchId))->toOthers();
        } catch (Throwable $e) {
            Log::warning('Meal portion broadcast failed after database commit.', [
                'meal_id' => $meal->id,
                'branch_id' => $branchId,
                'error' => $e->getMessage(),
            ]);
        }

        return $meal->fresh();
    }

    private function flushMenuCatalogCaches(?int $branchId): void
    {
        Cache::forget('pos_menu_catalog_all');
        if ($branchId !== null) {
            Cache::forget("pos_menu_catalog_{$branchId}");
        }
    }
}
