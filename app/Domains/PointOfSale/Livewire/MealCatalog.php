<?php

namespace App\Domains\PointOfSale\Livewire;

use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class MealCatalog extends Component
{
    public ?int $selectedCategoryId = null;

    public string $search = '';

    public ?string $lastMenuVersion = null;

    public function mount(): void
    {
        $branchId = auth()->user()?->branch_id ?? 1;
        $this->lastMenuVersion = $this->getCurrentMenuVersion($branchId);
    }

    public function getCurrentMenuVersion(?int $branchId): string
    {
        $branchVersion = Cache::get("branch_{$branchId}_menu_version", 0);
        $globalVersion = Cache::get('branch_global_menu_version', 0);

        return "{$branchVersion}:{$globalVersion}";
    }

    public function syncAvailability(): void
    {
        $branchId = auth()->user()?->branch_id ?? 1;
        $currentVersion = $this->getCurrentMenuVersion($branchId);

        if ($this->lastMenuVersion !== null && $this->lastMenuVersion === $currentVersion) {
            $this->skipRender();

            return;
        }

        $this->lastMenuVersion = $currentVersion;
        $this->dispatch('meal-availability-synced');
    }

    #[On('meal-updated')]
    #[On('meal-availability-toggled')]
    #[On('order-created')]
    #[On('order-voided')]
    public function refreshCatalog(): void
    {
        $branchId = auth()->user()?->branch_id ?? 1;
        $this->lastMenuVersion = $this->getCurrentMenuVersion($branchId);
        $this->dispatch('meal-availability-synced');
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
            ->forBranch(auth()->user()?->branch_id)
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
