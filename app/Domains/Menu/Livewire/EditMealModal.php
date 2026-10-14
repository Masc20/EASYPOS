<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Livewire\Attributes\On;
use Livewire\Component;

class EditMealModal extends Component
{
    public ?int $mealId = null;
    public string $name = '';
    public ?string $menu_code = null;
    public ?int $category_id = null;
    public ?string $price = '';
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

    #[On('edit-meal')]
    public function loadMeal(int $id): void
    {
        $meal = Meal::findOrFail($id);

        $this->mealId = $meal->id;
        $this->name = $meal->name;
        $this->menu_code = $meal->menu_code;
        $this->category_id = $meal->category_id;
        $this->price = (string) $meal->price;
        $this->description = $meal->description;
        $this->is_available = (bool) $meal->is_available;
        $this->track_stock = (bool) $meal->track_stock;
        $this->stock_quantity = $meal->stock_quantity;
        $this->resetValidation();

        $this->dispatch('open-modal', 'edit-meal-modal');
    }

    public function save(UpdateMealAction $action): void
    {
        $this->validate();

        if ($this->mealId) {
            $action->handle($this->mealId, [
                'name' => $this->name,
                'menu_code' => $this->menu_code,
                'category_id' => $this->category_id,
                'price' => $this->price,
                'description' => $this->description,
                'is_available' => $this->is_available,
                'track_stock' => $this->track_stock,
                'stock_quantity' => $this->stock_quantity,
            ]);
        }

        $this->dispatch('close-modal', 'edit-meal-modal');
        $this->dispatch('meal-saved');
    }

    public function render()
    {
        $categories = MealCategory::where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return view('livewire.menu.edit-meal-modal', [
            'categories' => $categories,
            'currencySymbol' => config('pos.currency.symbol', '₱'),
        ]);
    }
}
