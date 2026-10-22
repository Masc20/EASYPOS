<?php

namespace App\Domains\PointOfSale\Livewire;

use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Livewire\Attributes\On;
use Livewire\Component;

class MealCatalog extends Component
{
    public ?int $selectedCategoryId = null;
    public string $search = '';

    #[On('catalog-search')]
    public function handleSearch(string $query): void
    {
        $this->search = trim($query);
    }

    #[On('meal-updated')]
    #[On('meal-availability-toggled')]
    #[On('order-created')]
    public function refreshCatalog(): void
    {
        // Re-render when menu items or stock levels change
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->selectedCategoryId = $categoryId;
    }

    public function render()
    {
        $categories = MealCategory::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $meals = Meal::query()
            ->select([
                'id',
                'branch_id',
                'category_id',
                'name',
                'menu_code',
                'description',
                'price',
                'is_available',
                'track_stock',
                'stock_quantity',
                'is_active',
            ])
            ->with(['category:id,name,slug'])
            ->where('is_active', true)
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $soldOutCount = $meals->filter(fn (Meal $m) => $m->isSoldOut())->count();

        return view('livewire.point-of-sale.meal-catalog', [
            'categories' => $categories,
            'meals' => $meals,
            'soldOutCount' => $soldOutCount,
        ]);
    }
}
