<?php

namespace App\Livewire\Forms;

use App\Domains\Menu\Models\Meal;
use Livewire\Component;

class ProductSelect extends Component
{
    public ?int $selectedId = null;
    public ?Meal $selectedMeal = null;
    public string $search = '';
    public string $placeholder = 'Search meal or barcode...';
    public bool $disabled = false;
    public string $eventName = 'product-selected';
    public ?int $branchId = null;

    public function mount(
        ?int $selectedId = null,
        string $placeholder = 'Search meal or barcode...',
        bool $disabled = false,
        string $eventName = 'product-selected',
        ?int $branchId = null
    ): void {
        $this->selectedId = $selectedId;
        $this->placeholder = $placeholder;
        $this->disabled = $disabled;
        $this->eventName = $eventName;
        $this->branchId = $branchId;

        if ($this->selectedId) {
            $this->selectedMeal = Meal::find($this->selectedId);
        }
    }

    public function selectMeal(int $id): void
    {
        $meal = Meal::find($id);
        if (!$meal) {
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
            'track_stock' => (bool) $meal->track_stock,
            'stock_quantity' => (int) $meal->stock_quantity,
        ]);
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
        $this->selectedMeal = null;
        $this->search = '';

        $this->dispatch($this->eventName, mealId: null, meal: null);
    }

    public function render()
    {
        $results = collect();

        if (strlen(trim($this->search)) >= 1) {
            $query = Meal::query()
                ->where('is_available', true)
                ->where(function ($q) {
                    $term = '%' . trim($this->search) . '%';
                    $q->where('name', 'like', $term)
                        ->orWhere('menu_code', 'like', $term);
                });

            if ($this->branchId) {
                $query->where(function ($q) {
                    $q->where('branch_id', $this->branchId)
                        ->orWhereNull('branch_id');
                });
            }

            $results = $query->with('category')->limit(8)->get();
        }

        return view('livewire.forms.product-select', [
            'results' => $results,
        ]);
    }
}
