<?php

namespace App\Livewire\Forms;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Support\Constants\SystemConstants;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProductSelect extends Component
{
    public ?int $selectedId = null;

    public ?Meal $selectedMeal = null;

    public string $search = '';

    public string $placeholder = 'Search meal...';

    #[Locked]
    public bool $disabled = false;

    #[Locked]
    public string $eventName = 'product-selected';

    #[Locked]
    public ?int $branchId = null;

    private function resolveEffectiveBranchId(?int $requestedBranchId = null): ?int
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return -1;
        }

        return $user->effectiveBranchId($requestedBranchId);
    }

    private function isAuthorizedScope(?int $effectiveBranchId): bool
    {
        return auth()->check() && ($effectiveBranchId === null || $effectiveBranchId >= 0);
    }

    /**
     * @return Builder<Meal>
     */
    private function scopedMealsQuery(?int $effectiveBranchId): Builder
    {
        return Meal::query()
            ->when($effectiveBranchId !== null, fn (Builder $q) => $q->forBranch($effectiveBranchId))
            ->inStockForBranch($effectiveBranchId);
    }

    public function mount(
        ?int $selectedId = null,
        string $placeholder = 'Search meal...',
        bool $disabled = false,
        string $eventName = 'product-selected',
        ?int $branchId = null
    ): void {
        $effectiveBranchId = $this->resolveEffectiveBranchId($branchId);
        $this->placeholder = $placeholder;
        $this->disabled = $disabled;
        $this->eventName = $eventName;
        $this->branchId = $effectiveBranchId;

        if ($selectedId && $this->isAuthorizedScope($effectiveBranchId)) {
            $meal = $this->scopedMealsQuery($effectiveBranchId)->find($selectedId);
            $this->selectedMeal = $meal;
            $this->selectedId = $meal?->id;
        } else {
            $this->selectedId = null;
            $this->selectedMeal = null;
        }
    }

    public function selectMeal(int $id): void
    {
        $effectiveBranchId = $this->resolveEffectiveBranchId($this->branchId);
        if (! $this->isAuthorizedScope($effectiveBranchId)) {
            abort(403, 'Unauthorized.');
        }

        if ($this->disabled) {
            return;
        }

        $meal = $this->scopedMealsQuery($effectiveBranchId)->find($id);
        if (! $meal) {
            return;
        }

        $this->selectedId = $meal->id;
        $this->selectedMeal = $meal;
        $this->search = '';

        $this->dispatch($this->eventName, mealId: $meal->id, meal: [
            'id' => $meal->id,
            'name' => $meal->name,
            'price' => (float) $meal->price,
            'menu_code' => $meal->menu_code,
            'track_stock' => $meal->trackStockForBranch($effectiveBranchId),
            'stock_quantity' => $meal->stockQuantityForBranch($effectiveBranchId),
        ]);
    }

    public function clearSelection(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->selectedId = null;
        $this->selectedMeal = null;
        $this->search = '';

        $this->dispatch($this->eventName, mealId: null, meal: null);
    }

    public function render()
    {
        $effectiveBranchId = $this->resolveEffectiveBranchId($this->branchId);
        $results = collect();

        if ($this->isAuthorizedScope($effectiveBranchId) && strlen(trim($this->search)) >= 1) {
            $results = $this->scopedMealsQuery($effectiveBranchId)
                ->when($effectiveBranchId !== null, fn (Builder $q) => $q->with([
                    'branchAvailabilities' => fn ($rel) => $rel->where('branch_id', $effectiveBranchId),
                ]))
                ->where(function (Builder $q) {
                    $term = '%'.trim($this->search).'%';
                    $q->where('name', 'like', $term)
                        ->orWhere('menu_code', 'like', $term);
                })
                ->with('category')
                ->limit(8)
                ->get();
        }

        return view('livewire.forms.product-select', [
            'results' => $results,
            'effectiveBranchId' => $effectiveBranchId,
            'currencySymbol' => SystemConstants::CURRENCY_SYMBOL,
        ]);
    }
}
