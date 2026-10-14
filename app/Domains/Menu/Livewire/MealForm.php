<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class MealForm extends Component
{
    public bool $isOpen = false;
    public ?int $mealId = null;
    public string $name = '';
    public ?string $menu_code = null;
    public ?int $category_id = null;
    public ?string $price = '';
    public ?string $cost_price = '';
    public ?string $description = null;
    public bool $is_available = true;
    public bool $track_stock = false;
    public ?int $stock_quantity = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'menu_code' => 'nullable|string|max:20|unique:meals,menu_code,' . ($this->mealId ?? 'NULL') . ',id',
            'category_id' => 'required|exists:meal_categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'is_available' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'nullable|required_if:track_stock,true|integer|min:0',
        ];
    }

    protected $messages = [
        'name.required' => 'Please provide the name of the dish.',
        'category_id.required' => 'Please select a menu category.',
        'category_id.exists' => 'The selected category is invalid.',
        'price.required' => 'Selling price in Philippine Pesos (₱) is required.',
        'price.numeric' => 'Selling price must be a valid number.',
        'stock_quantity.required_if' => 'Please specify daily portion quantity when stock countdown is enabled.',
    ];

    public function mount(?int $mealId = null): void
    {
        if ($mealId) {
            $this->loadMeal($mealId);
        }
    }

    #[On('open-meal-modal')]
    public function openNewMealModal(): void
    {
        $this->resetForm();
        $this->isOpen = true;
        $this->dispatch('open-modal', 'meal-modal');
    }

    #[On('edit-meal')]
    public function loadMeal(int $id): void
    {
        $meal = Meal::findOrFail($id);

        $this->mealId = $meal->id;
        $this->name = $meal->name;
        $this->menu_code = $meal->menu_code;
        $this->category_id = $meal->category_id;
        $this->price = (string) $meal->price;
        $this->cost_price = $meal->cost_price ? (string) $meal->cost_price : '';
        $this->description = $meal->description;
        $this->is_available = (bool) $meal->is_available;
        $this->track_stock = (bool) $meal->track_stock;
        $this->stock_quantity = $meal->stock_quantity;
        $this->isOpen = true;
        $this->dispatch('open-modal', 'meal-modal');
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetForm();
        $this->dispatch('close-modal', 'meal-modal');
    }

    #[Computed]
    public function marginInfo(): ?array
    {
        $price = (float) $this->price;
        $cost = (float) $this->cost_price;

        if ($price > 0 && $cost > 0) {
            $profit = $price - $cost;
            $marginPercent = round(($profit / $price) * 100);

            return [
                'profit' => $profit,
                'margin' => $marginPercent,
                'is_profitable' => $profit >= 0,
            ];
        }

        return null;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => trim($this->name),
            'menu_code' => $this->menu_code ? strtoupper(trim($this->menu_code)) : null,
            'category_id' => $this->category_id,
            'price' => (float) $this->price,
            'cost_price' => filled($this->cost_price) ? (float) $this->cost_price : null,
            'description' => $this->description ? trim($this->description) : null,
            'is_available' => $this->is_available,
            'track_stock' => $this->track_stock,
            'stock_quantity' => $this->track_stock ? (int) $this->stock_quantity : null,
            'created_by' => auth()->id(),
        ];

        if ($this->mealId) {
            app(\App\Domains\Menu\Actions\UpdateMealAction::class)->handle($this->mealId, $data);
        } else {
            app(\App\Domains\Menu\Actions\CreateMealAction::class)->handle($data);
        }

        $this->isOpen = false;
        $this->dispatch('close-modal', 'meal-modal');
        $this->dispatch('meal-saved');
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->mealId = null;
        $this->name = '';
        $this->menu_code = null;
        $firstCategory = MealCategory::where('is_active', true)->orderBy('display_order')->first();
        $this->category_id = $firstCategory?->id;
        $this->price = '';
        $this->cost_price = '';
        $this->description = null;
        $this->is_available = true;
        $this->track_stock = false;
        $this->stock_quantity = null;
        $this->resetValidation();
    }

    public function render()
    {
        $categories = MealCategory::where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return view('livewire.menu.meal-form', [
            'categories' => $categories,
            'currencySymbol' => config('pos.currency.symbol', '₱'),
        ]);
    }
}
