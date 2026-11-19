<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Actions\DeleteMealAction;
use App\Domains\Menu\Actions\ToggleMealAvailabilityAction;
use App\Domains\Menu\Actions\UpdateBranchPortionsAction;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Constants\SystemConstants;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class MealCatalogTable extends Component
{
    use WithPagination;

    public ?string $selectedCategory = null;

    public string $search = '';

    public string $statusFilter = 'all'; // all, in_stock, sold_out

    public int $perPage = 24;

    #[Locked]
    public ?int $portionsMealId = null;

    public string $portionsMealName = '';

    public bool $portionsTrackStock = false;

    public ?int $portionsQuantity = null;

    public function updatedPerPage(): void
    {
        $this->perPage = max(1, min(100, (int) $this->perPage));
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedCategory(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Filter catalog by category slug.
     */
    public function filterByCategory(?string $slug = null): void
    {
        $this->selectedCategory = $slug;
        $this->resetPage();
    }

    /**
     * Filter catalog by availability status.
     */
    public function filterByStatus(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    /**
     * Refresh catalog when a meal is added/edited via MealForm.
     */
    #[On('meal-saved')]
    public function refreshCatalog(): void
    {
        // Livewire re-renders automatically
    }

    /**
     * Trigger edit mode in MealForm.
     */
    public function editMeal(int $mealId): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can('menu.manage') && ! $user->isOwner())) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isBranchScoped() && ($user->branch_id === null || $user->effectiveBranchId() < 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        $meal = Meal::findOrFail($mealId);
        if ($user->isBranchScoped() && ($meal->branch_id === null || (int) $meal->branch_id !== $user->effectiveBranchId())) {
            abort(403, 'Unauthorized. Global or other-branch menu dishes cannot be modified by branch staff.');
        }

        $this->dispatch('edit-meal', id: $mealId);
    }

    /**
     * Instant 86'd / Sold Out toggle for kitchen & chef.
     */
    public function toggleAvailability(int $mealId, ToggleMealAvailabilityAction $action): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can('menu.manage') && ! $user->isOwner())) {
            abort(403, 'Unauthorized.');
        }
        if ($user->isBranchScoped() && ($user->branch_id === null || $user->effectiveBranchId() < 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        $action->handle($mealId);
        $this->dispatch('meal-saved');
    }

    /**
     * Open branch-scoped portions modal for a meal (allowed for shared HQ dishes too).
     */
    public function openPortionsModal(int $mealId): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can('menu.manage') && ! $user->isOwner())) {
            abort(403, 'Unauthorized.');
        }

        $isBranchScoped = $user->isBranchScoped();
        $branchId = $isBranchScoped ? $user->effectiveBranchId() : null;
        if ($isBranchScoped && ($user->branch_id === null || $branchId < 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        $meal = Meal::findOrFail($mealId);

        if ($isBranchScoped && $meal->branch_id !== null && (int) $meal->branch_id !== $branchId) {
            abort(403, 'Unauthorized. You cannot modify portions for a dish belonging to another branch.');
        }

        $this->portionsMealId = $meal->id;
        $this->portionsMealName = $meal->name;
        $this->portionsTrackStock = $meal->trackStockForBranch($branchId);
        $this->portionsQuantity = $meal->stockQuantityForBranch($branchId);
        $this->resetValidation();

        $this->dispatch('open-modal', 'branch-portions-modal');
    }

    /**
     * Save branch-scoped daily portion limits for the selected dish.
     */
    public function savePortions(UpdateBranchPortionsAction $action): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can('menu.manage') && ! $user->isOwner())) {
            abort(403, 'Unauthorized.');
        }
        if ($user->isBranchScoped() && ($user->branch_id === null || $user->effectiveBranchId() < 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        if ($this->portionsMealId === null) {
            return;
        }

        $this->validate([
            'portionsTrackStock' => 'boolean',
            'portionsQuantity' => 'nullable|required_if:portionsTrackStock,true|integer|min:0',
        ], [
            'portionsQuantity.required_if' => 'Please specify daily portion quantity when portion tracking is enabled.',
        ]);

        $action->handle(
            $this->portionsMealId,
            $this->portionsTrackStock,
            $this->portionsQuantity
        );

        $this->portionsMealId = null;
        $this->portionsMealName = '';
        $this->dispatch('close-modal', 'branch-portions-modal');
        $this->dispatch('meal-saved');
        $this->dispatch('menu-alert', type: 'success', message: 'Branch portions updated successfully.');
    }

    /**
     * Soft delete / archive a meal dish.
     */
    public function deleteMeal(int $mealId, DeleteMealAction $action): void
    {
        $user = auth()->user();
        if (! $user || (! $user->can('menu.manage') && ! $user->isOwner())) {
            abort(403, 'Unauthorized.');
        }
        if ($user->isBranchScoped() && ($user->branch_id === null || $user->effectiveBranchId() < 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        $action->handle($mealId);
        $this->dispatch('meal-saved');
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

        $categories = MealCategory::withCount([
            'meals' => fn ($query) => $isBranchScoped ? $query->forBranch($branchId) : $query,
        ])
            ->when($isBranchScoped, fn ($q) => $q->forBranch($branchId))
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $totalDishesCount = (int) $categories->sum('meals_count');

        $with = ['category'];
        if ($isBranchScoped && $branchId !== null) {
            $with['branchAvailabilities'] = fn ($q) => $q->where('branch_id', $branchId);
        } else {
            $with[] = 'branch';
        }

        $query = Meal::with($with)
            ->when($isBranchScoped, fn ($q) => $q->forBranch($branchId))
            ->when($this->selectedCategory, function ($query) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $this->selectedCategory));
            })
            ->when($this->statusFilter === 'in_stock', function ($query) use ($branchId) {
                $query->inStockForBranch($branchId);
            })
            ->when($this->statusFilter === 'sold_out', function ($query) use ($branchId) {
                $query->eightSixForBranch($branchId);
            })
            ->when($this->search, function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('menu_code', 'like', $term);
                });
            })
            ->orderBy('name');

        $this->perPage = max(1, min(100, (int) $this->perPage));
        $meals = $query->simplePaginate($this->perPage);

        return view('livewire.menu.meal-catalog-table', [
            'categories' => $categories,
            'meals' => $meals,
            'totalDishesCount' => $totalDishesCount,
            'currencySymbol' => SystemConstants::CURRENCY_SYMBOL,
            'branchId' => $branchId,
            'isBranchScoped' => $isBranchScoped,
        ]);
    }
}
