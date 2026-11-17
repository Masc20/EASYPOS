<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class EditMealModal extends Component
{
    public bool $isOpen = false;
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
        $normalizedCode = filled($this->menu_code)
            ? strtoupper(trim((string) $this->menu_code))
            : null;

        $loadedMeal = $this->mealId !== null ? Meal::find($this->mealId) : null;
        $existingNormalizedCode = ($loadedMeal && filled($loadedMeal->menu_code))
            ? strtoupper(trim((string) $loadedMeal->menu_code))
            : null;

        // Allow saving an unmodified menu_code even if legacy duplicate rows exist in the table.
        $isUnchangedExistingCode = $loadedMeal !== null
            && $normalizedCode !== null
            && $existingNormalizedCode !== null
            && $normalizedCode === $existingNormalizedCode;

        $menuCodeRules = ['nullable', 'string', 'max:20'];
        if (! $isUnchangedExistingCode) {
            $menuCodeRules[] = Rule::unique('meals', 'menu_code')
                ->ignore($this->mealId)
                ->whereNull('deleted_at');
        }

        return [
            'name' => 'required|string|max:150',
            'menu_code' => $menuCodeRules,
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

    public function mount(?int $mealId = null): void
    {
        if ($mealId) {
            $this->loadMeal($mealId);
        }
    }

    #[On('edit-meal')]
    public function loadMeal(int $id): void
    {
        $user = auth()->user();
        if ($user && ! $user->can('menu.manage') && ! $user->isOwner()) {
            abort(403, 'Unauthorized.');
        }

        $meal = Meal::findOrFail($id);

        if ($user && $user->isBranchScoped()) {
            if ($meal->branch_id === null) {
                abort(403, 'Unauthorized. Global menu dishes can only be modified by system administrators.');
            }
            if ((int) $meal->branch_id !== (int) $user->branch_id) {
                abort(403, 'Unauthorized. You cannot modify a dish belonging to another branch.');
            }
        }

        $this->mealId = $meal->id;
        $this->name = $meal->name;
        $this->menu_code = $meal->menu_code;
        $this->category_id = $meal->category_id;
        $this->price = (string) $meal->price;
        $this->description = $meal->description;
        $this->is_available = (bool) $meal->is_available;
        $this->track_stock = (bool) $meal->track_stock;
        $this->stock_quantity = $meal->stock_quantity;
        $this->isOpen = true;
        $this->resetValidation();

        $this->dispatch('open-modal', 'edit-meal-modal');
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->mealId = null;
        $this->name = '';
        $this->menu_code = null;
        $this->category_id = null;
        $this->price = '';
        $this->description = null;
        $this->is_available = true;
        $this->track_stock = false;
        $this->stock_quantity = null;
        $this->resetValidation();
        $this->dispatch('close-modal', 'edit-meal-modal');
    }

    public function updatedCategoryId($value): void
    {
        if ($value && blank($this->menu_code)) {
            $this->menu_code = \App\Support\Services\CodeGeneratorService::generateMenuCode((int) $value);
        }
    }

    public function save(UpdateMealAction $action): void
    {
        $user = auth()->user();
        if ($user && ! $user->can('menu.manage') && ! $user->isOwner()) {
            abort(403, 'Unauthorized.');
        }

        if ($this->mealId === null) {
            $this->closeModal();

            return;
        }

        $this->menu_code = filled($this->menu_code)
            ? strtoupper(trim((string) $this->menu_code))
            : null;

        $this->validate();

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

        $this->closeModal();
        $this->dispatch('meal-saved');
    }

    public function render()
    {
        $categories = ($this->mealId !== null && $this->isOpen)
            ? Cache::remember('meal_categories_active', 3600, function () {
                return MealCategory::where('is_active', true)->orderBy('display_order')->get();
            })
            : collect();

        return view('livewire.menu.edit-meal-modal', [
            'categories' => $categories,
            'currencySymbol' => config('pos.currency.symbol', '₱'),
        ]);
    }
}
