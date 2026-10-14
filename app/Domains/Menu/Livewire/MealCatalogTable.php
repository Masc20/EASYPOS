<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Livewire\Attributes\On;
use Livewire\Component;

class MealCatalogTable extends Component
{
    public ?string $selectedCategory = null;
    public string $search = '';
    public string $statusFilter = 'all'; // all, in_stock, sold_out

    /**
     * Filter catalog by category slug.
     */
    public function filterByCategory(?string $slug = null): void
    {
        $this->selectedCategory = $slug;
    }

    /**
     * Filter catalog by availability status.
     */
    public function filterByStatus(string $status): void
    {
        $this->statusFilter = $status;
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
        $this->dispatch('edit-meal', id: $mealId);
    }

    /**
     * Instant 86'd / Sold Out toggle for kitchen & chef.
     */
    public function toggleAvailability(int $mealId, \App\Domains\Menu\Actions\ToggleMealAvailabilityAction $action): void
    {
        $action->handle($mealId);
        $this->dispatch('meal-saved');
    }

    /**
     * Soft delete / archive a meal dish.
     */
    public function deleteMeal(int $mealId, \App\Domains\Menu\Actions\DeleteMealAction $action): void
    {
        $action->handle($mealId);
        $this->dispatch('meal-saved');
    }

    public function render()
    {
        $categories = MealCategory::withCount('meals')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $meals = Meal::with('category')
            ->when($this->selectedCategory, function ($query) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $this->selectedCategory));
            })
            ->when($this->statusFilter === 'in_stock', function ($query) {
                $query->where('is_available', true);
            })
            ->when($this->statusFilter === 'sold_out', function ($query) {
                $query->where('is_available', false);
            })
            ->when($this->search, function ($query) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('menu_code', 'like', $term);
                });
            })
            ->orderBy('name')
            ->get();

        $totalDishesCount = Meal::count();

        return view('livewire.menu.meal-catalog-table', [
            'categories' => $categories,
            'meals' => $meals,
            'totalDishesCount' => $totalDishesCount,
            'currencySymbol' => config('pos.currency.symbol', '₱'),
        ]);
    }
}
