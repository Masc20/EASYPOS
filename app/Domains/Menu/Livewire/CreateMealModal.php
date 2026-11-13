<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Services\CodeGeneratorService;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateMealModal extends Component
{
    public bool $isOpen = false;
    public string $name = '';
    public ?string $menu_code = null;
    public ?string $lastAutoCode = null;
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
        // Defer category loading and default category assignment until opened
    }

    protected function getCachedActiveCategories()
    {
        return Cache::remember('meal_categories_active', 3600, function () {
            return MealCategory::where('is_active', true)->orderBy('display_order')->get();
        });
    }

    private function setDefaultCategory(): void
    {
        $firstCategory = $this->getCachedActiveCategories()->first();
        $this->category_id = $firstCategory?->id;
    }

    public function generateCode(): void
    {
        if (! $this->category_id) {
            $this->setDefaultCategory();
        }

        if ($this->category_id && (blank($this->menu_code) || $this->menu_code === $this->lastAutoCode)) {
            $code = CodeGeneratorService::generateMenuCode((int) $this->category_id);
            $this->menu_code = $code;
            $this->lastAutoCode = $code;
        }
    }

    public function updatedCategoryId($value): void
    {
        if (! $value) {
            if ($this->menu_code === $this->lastAutoCode) {
                $this->menu_code = null;
            }
            return;
        }

        if (blank($this->menu_code) || $this->menu_code === $this->lastAutoCode) {
            $code = CodeGeneratorService::generateMenuCode((int) $value);
            $this->menu_code = $code;
            $this->lastAutoCode = $code;
        }
    }

    #[On('open-create-meal-modal')]
    public function openCreateModal(): void
    {
        $this->isOpen = true;
        $this->resetForm();
        $this->setDefaultCategory();
        $this->generateCode();
        $this->dispatch('open-modal', 'create-meal-modal');
    }

    public function openModal(): void
    {
        $this->openCreateModal();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetForm();
        $this->dispatch('close-modal', 'create-meal-modal');
    }

    public function save(CreateMealAction $action): void
    {
        $user = auth()->user();
        if ($user && ! $user->can('menu.manage') && ! $user->isOwner()) {
            abort(403, 'Unauthorized.');
        }

        if (blank($this->menu_code) && $this->category_id) {
            $this->menu_code = CodeGeneratorService::generateMenuCode((int) $this->category_id);
        }

        $this->validate();

        $action->handle([
            'name' => $this->name,
            'menu_code' => $this->menu_code,
            'category_id' => $this->category_id,
            'branch_id' => auth()->user()?->branch_id,
            'price' => $this->price,
            'description' => $this->description,
            'is_available' => $this->is_available,
            'track_stock' => $this->track_stock,
            'stock_quantity' => $this->stock_quantity,
            'created_by' => auth()->id(),
        ]);

        $this->isOpen = false;
        $this->dispatch('close-modal', 'create-meal-modal');
        $this->dispatch('meal-saved');
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->menu_code = null;
        $this->lastAutoCode = null;
        if ($this->isOpen) {
            $this->setDefaultCategory();
        } else {
            $this->category_id = null;
        }
        $this->price = '';
        $this->description = null;
        $this->is_available = true;
        $this->track_stock = false;
        $this->stock_quantity = null;
        $this->resetValidation();
    }

    public function render()
    {
        $categories = $this->isOpen
            ? $this->getCachedActiveCategories()
            : collect();

        return view('livewire.menu.create-meal-modal', [
            'categories' => $categories,
            'categoryCodeMap' => $this->isOpen ? CodeGeneratorService::getCategoryMenuCodeMap() : [],
            'currencySymbol' => config('pos.currency.symbol', '₱'),
        ]);
    }
}
