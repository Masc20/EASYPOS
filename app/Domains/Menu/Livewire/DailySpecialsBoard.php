<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Support\Constants\SystemConstants;
use Livewire\Attributes\On;
use Livewire\Component;

class DailySpecialsBoard extends Component
{
    #[On('meal-saved')]
    public function refreshBoard(): void
    {
        // Re-renders automatically
    }

    public function render()
    {
        $user = auth()->user();
        if (! $user instanceof User || (! $user->can('menu.view') && ! $user->can('menu.manage') && ! $user->isOwner())) {
            abort(403, 'Unauthorized.');
        }

        $isBranchScoped = $user->isBranchScoped();
        $branchId = $isBranchScoped ? $user->effectiveBranchId() : null;
        if ($isBranchScoped && ($user->branch_id === null || $branchId < 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        $limitedSpecials = Meal::query()
            ->when($isBranchScoped, fn ($q) => $q->forBranch($branchId))
            ->when($isBranchScoped && $branchId !== null, fn ($q) => $q->with([
                'branchAvailabilities' => fn ($rel) => $rel->where('branch_id', $branchId),
            ]))
            ->inStockForBranch($branchId)
            ->get()
            ->filter(fn (Meal $meal) => $meal->trackStockForBranch($branchId))
            ->sortBy(fn (Meal $meal) => $meal->stockQuantityForBranch($branchId) ?? PHP_INT_MAX)
            ->take(15)
            ->values();

        $soldOutCount = Meal::query()
            ->when($isBranchScoped, fn ($q) => $q->forBranch($branchId))
            ->eightSixForBranch($branchId)
            ->count();

        return view('livewire.menu.daily-specials-board', [
            'limitedSpecials' => $limitedSpecials,
            'soldOutCount' => $soldOutCount,
            'branchId' => $branchId,
            'currencySymbol' => SystemConstants::CURRENCY_SYMBOL,
        ]);
    }
}
