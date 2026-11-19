<?php

namespace App\Domains\Menu\Livewire;

use App\Domains\Menu\Actions\CreateMealAction;
use App\Domains\Menu\Actions\UpdateMealAction;
use App\Domains\Menu\Livewire\Concerns\InteractsWithMealForm;
use App\Support\Constants\SystemConstants;
use App\Support\Services\CodeGeneratorService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class MealForm extends Component
{
    use InteractsWithMealForm;

    public bool $isOpen = false;

    #[Locked]
    public ?int $mealId = null;

    public string $name = '';

    public ?string $menu_code = null;

    public ?string $lastAutoCode = null;

    public ?int $category_id = null;

    public ?string $price = '';

    public ?string $cost_price = '';

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
            'cost_price' => 'nullable|numeric|min:0',
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

    public function updatedCategoryId($value): void
    {
        $this->requireMenuManageUser();

        if (! $this->mealId && filled($value) && (blank($this->menu_code) || $this->menu_code === $this->lastAutoCode)) {
            $code = CodeGeneratorService::generateMenuCode((int) $value);
            $this->menu_code = $code;
            $this->lastAutoCode = $code;
        }
    }

    #[On('open-meal-modal')]
    public function openNewMealModal(): void
    {
        $this->requireMenuManageUser();
        $this->resetForm();
        $this->isOpen = true;
        $this->dispatch('open-modal', 'meal-modal');
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
        $this->cost_price = $meal->cost_price ? (string) $meal->cost_price : '';
        $this->description = $meal->description;
        $this->is_available = (bool) $meal->is_available;
        $this->track_stock = (bool) $meal->track_stock;
        $this->stock_quantity = $meal->stock_quantity;
        $this->isOpen = true;
        $this->resetValidation();
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
        $user = $this->requireMenuManageUser();

        $this->menu_code = $this->normalizeMenuCode($this->menu_code);

        $this->sanitizeMealFormInputs();

        $this->validate();

        $data = [
            'name' => trim($this->name),
            'menu_code' => $this->menu_code,
            'category_id' => $this->category_id,
            'branch_id' => $user->isBranchScoped() ? $user->effectiveBranchId() : $user->branch_id,
            'price' => (float) $this->price,
            'cost_price' => filled($this->cost_price) ? (float) $this->cost_price : null,
            'description' => $this->description ? trim($this->description) : null,
            'is_available' => $this->is_available,
            'track_stock' => $this->track_stock,
            'stock_quantity' => $this->track_stock ? (int) $this->stock_quantity : null,
            'created_by' => auth()->id(),
        ];

        if ($this->mealId) {
            app(UpdateMealAction::class)->handle($this->mealId, $data);
        } else {
            app(CreateMealAction::class)->handle($data);
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
        $this->lastAutoCode = null;
        $categories = $this->getScopedActiveCategories();
        $this->category_id = $categories->first()?->id;
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
        $categories = $this->getScopedActiveCategories();

        return view('livewire.menu.meal-form', [
            'categories' => $categories,
            'currencySymbol' => SystemConstants::CURRENCY_SYMBOL,
        ]);
    }
}
