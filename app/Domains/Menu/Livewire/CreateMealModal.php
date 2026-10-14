<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Models\MealCategory;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateMealModal extends Component
{
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
            'menu_code' => 'nullable|string|max:20|unique:meals,menu_code',
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

    public function mount(): void
    {
        $this->setDefaultCategory();
    }

    private function setDefaultCategory(): void
    {
        $firstCategory = MealCategory::where('is_active', true)->orderBy('display_order')->first();
        $this->category_id = $firstCategory?->id;
    }

    #[On('open-create-meal-modal')]
    public function openModal(): void
    {
        $this->resetForm();
        $this->dispatch('open-modal', 'create-meal-modal');
    }

    public function save(CreateMealAction $action): void
    {
        $this->validate();

        $action->handle([
            'name' => $this->name,
            'menu_code' => $this->menu_code,
            'category_id' => $this->category_id,
            'price' => $this->price,
            'description' => $this->description,
            'is_available' => $this->is_available,
            'track_stock' => $this->track_stock,
            'stock_quantity' => $this->stock_quantity,
            'created_by' => auth()->id(),
        ]);

        $this->dispatch('close-modal', 'create-meal-modal');
        $this->dispatch('meal-saved');
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->menu_code = null;
        $this->setDefaultCategory();
        $this->price = '';
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

        return view('livewire.menu.create-meal-modal', [
            'categories' => $categories,
            'currencySymbol' => config('pos.currency.symbol', '₱'),
        ]);
    }
}
