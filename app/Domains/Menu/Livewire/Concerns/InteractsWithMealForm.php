<?php

namespace App\Domains\Menu\Livewire\Concerns;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Constants\SystemConstants;
use App\Support\InputSanitizer;
use Illuminate\Validation\Rule;

trait InteractsWithMealForm
{
    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Please provide the name of the dish.',
            'category_id.required' => 'Please select a menu category.',
            'category_id.exists' => 'The selected category is invalid.',
            'price.required' => 'Selling price in Philippine Pesos ('.SystemConstants::CURRENCY_SYMBOL.') is required.',
            'price.numeric' => 'Selling price must be a valid number.',
            'stock_quantity.required_if' => 'Please specify daily portion quantity when stock countdown is enabled.',
        ];
    }

    protected function normalizeMenuCode(?string $code): ?string
    {
        return filled($code) ? strtoupper(trim((string) $code)) : null;
    }

    protected function categoryBelongsToBranchRule(bool $isBranchScoped, ?int $effectiveBranchId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($isBranchScoped, $effectiveBranchId): void {
            if (! $isBranchScoped || blank($value)) {
                return;
            }

            $allowed = MealCategory::query()
                ->where('id', (int) $value)
                ->forBranch($effectiveBranchId)
                ->exists();

            if (! $allowed) {
                $fail('The selected category does not belong to your branch.');
            }
        };
    }

    /**
     * @return array<int, mixed>
     */
    protected function editMenuCodeRules(?int $mealId, ?string $menuCode): array
    {
        $normalizedCode = $this->normalizeMenuCode($menuCode);

        $loadedMeal = $mealId !== null ? Meal::find($mealId) : null;
        $existingNormalizedCode = $loadedMeal ? $this->normalizeMenuCode($loadedMeal->menu_code) : null;

        // Allow saving an unmodified menu_code even if legacy duplicate rows exist in the table.
        $isUnchangedExistingCode = $loadedMeal !== null
            && $normalizedCode !== null
            && $existingNormalizedCode !== null
            && $normalizedCode === $existingNormalizedCode;

        $menuCodeRules = ['nullable', 'string', 'max:20'];
        if (! $isUnchangedExistingCode) {
            $menuCodeRules[] = Rule::unique('meals', 'menu_code')
                ->ignore($mealId)
                ->whereNull('deleted_at');
        }

        return $menuCodeRules;
    }

    protected function sanitizeMealFormInputs(): void
    {
        $this->name = InputSanitizer::clean($this->name) ?? '';
        $this->description = InputSanitizer::clean($this->description);
    }

    protected function requireAuthenticatedMenuUser(): User
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            abort(403, 'Unauthorized.');
        }

        if (! $user->can('menu.view') && ! $user->can('menu.manage') && ! $user->isOwner()) {
            abort(403, 'Unauthorized.');
        }

        if ($user->isBranchScoped() && ($user->branch_id === null || $user->effectiveBranchId() < 0)) {
            abort(403, 'Unauthorized. Branch assignment required.');
        }

        return $user;
    }

    protected function requireMenuManageUser(): User
    {
        $user = $this->requireAuthenticatedMenuUser();
        if (! $user->can('menu.manage') && ! $user->isOwner()) {
            abort(403, 'Unauthorized.');
        }

        return $user;
    }

    protected function authorizeAndFindEditableMeal(int $id, User $user): Meal
    {
        $meal = Meal::findOrFail($id);

        if ($user->isBranchScoped()) {
            if ($meal->branch_id === null) {
                abort(403, 'Unauthorized. Global menu dishes can only be modified by system administrators.');
            }
            if ((int) $meal->branch_id !== $user->effectiveBranchId()) {
                abort(403, 'Unauthorized. You cannot modify a dish belonging to another branch.');
            }
        }

        return $meal;
    }

    protected function getScopedActiveCategories()
    {
        $user = $this->requireAuthenticatedMenuUser();
        $isBranchScoped = $user->isBranchScoped();

        return MealCategory::cachedActiveForBranch(
            $isBranchScoped ? $user->effectiveBranchId() : null,
            includeAllBranches: ! $isBranchScoped
        );
    }
}
