<?php

namespace App\Domains\PointOfSale\Livewire;

use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class MealCatalog extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->user()?->can('pos.access'), 403);
    }

    public ?int $selectedCategoryId = null;

    public string $search = '';

    public ?string $lastMenuVersion = null;

    protected function resolveBranchId(): ?int
    {
        $user = auth()->user();
        if ($user && $user->isBranchScoped()) {
            return $user->effectiveBranchId();
        }

        return $user?->branch_id;
    }

    public function mount(): void
    {
        $branchId = $this->resolveBranchId();
        $this->lastMenuVersion = $this->getCurrentMenuVersion($branchId);
    }

    public function getCurrentMenuVersion(?int $branchId): string
    {
        $branchVersion = $branchId !== null ? Cache::get("branch_{$branchId}_menu_version", 0) : 0;
        $globalVersion = Cache::get('branch_global_menu_version', 0);

        return "{$branchVersion}:{$globalVersion}";
    }

    public function syncAvailability(): void
    {
        $user = auth()->user();
        abort_unless($user?->can('pos.access'), 403);

        $branchId = $this->resolveBranchId();
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
        $branchId = $this->resolveBranchId();
        $this->lastMenuVersion = $this->getCurrentMenuVersion($branchId);
        $this->dispatch('meal-availability-synced');
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->selectedCategoryId = $categoryId;
    }

    public function render()
    {
        $branchId = $this->resolveBranchId();

        $categories = MealCategory::query()
            ->when($branchId !== null, fn ($q) => $q->forBranch($branchId))
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        $meals = Meal::query()
            ->forBranch($branchId)
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
            ->with([
                'category:id,name,slug',
                ...($branchId !== null ? ['branchAvailabilities' => fn ($q) => $q->where('branch_id', $branchId)] : []),
            ])
            ->where('is_active', true)
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $soldOutCount = $meals->filter(fn (Meal $m) => $m->isSoldOutForBranch($branchId))->count();

        return view('livewire.point-of-sale.meal-catalog', [
            'categories' => $categories,
            'meals' => $meals,
            'soldOutCount' => $soldOutCount,
            'branchId' => $branchId,
        ]);
    }
}
