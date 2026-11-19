<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Livewire\Concerns\InteractsWithMealForm;
use App\Support\Constants\SystemConstants;
use App\Support\Services\CodeGeneratorService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class EditMealModal extends Component
{
    use InteractsWithMealForm;

    public bool $isOpen = false;

    #[Locked]
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
        $user = $this->requireAuthenticatedMenuUser();
        $isBranchScoped = $user->isBranchScoped();
        $effectiveBranchId = $isBranchScoped ? $user->effectiveBranchId() : null;

        return [
            'name' => 'required|string|max:150',
            'menu_code' => $this->editMenuCodeRules($this->mealId, $this->menu_code),
            'category_id' => [
                'required',
                'exists:meal_categories,id',
                $this->categoryBelongsToBranchRule($isBranchScoped, $effectiveBranchId),
            ],
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'is_available' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'nullable|required_if:track_stock,true|integer|min:0',
        ];
    }

    public function mount(?int $mealId = null): void
    {
        if ($mealId) {
            $this->loadMeal($mealId);
        }
    }

    #[On('edit-meal')]
    public function loadMeal(int $id): void
    {
        $user = $this->requireMenuManageUser();
        $meal = $this->authorizeAndFindEditableMeal($id, $user);

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
        $this->requireMenuManageUser();

        if ($value && blank($this->menu_code)) {
            $this->menu_code = CodeGeneratorService::generateMenuCode((int) $value);
        }
    }

    public function save(UpdateMealAction $action): void
    {
        $this->requireMenuManageUser();

        if ($this->mealId === null) {
            $this->closeModal();

            return;
        }

        $this->menu_code = $this->normalizeMenuCode($this->menu_code);

        $this->sanitizeMealFormInputs();

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
        $this->requireAuthenticatedMenuUser();

        $categories = ($this->mealId !== null && $this->isOpen)
            ? $this->getScopedActiveCategories()
            : collect();

        return view('livewire.menu.edit-meal-modal', [
            'categories' => $categories,
            'currencySymbol' => SystemConstants::CURRENCY_SYMBOL,
        ]);
    }
}
