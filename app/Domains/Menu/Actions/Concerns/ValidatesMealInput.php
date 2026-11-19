<?php

namespace App\Domains\Menu\Actions\Concerns;

use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\MealCategory;
use App\Support\InputSanitizer;
use Illuminate\Validation\ValidationException;

trait ValidatesMealInput
{
    protected function validateCategoryForBranch(User $user, mixed $categoryId, ?int $targetBranchId): MealCategory
    {
        $category = is_numeric($categoryId) ? MealCategory::find((int) $categoryId) : null;
        if (! $category instanceof MealCategory) {
            throw ValidationException::withMessages([
                'category_id' => 'The selected category is invalid.',
            ]);
        }

        if ($category->branch_id !== null && ($targetBranchId === null || (int) $category->branch_id !== $targetBranchId)) {
            if ($user->isBranchScoped()) {
                abort(403, 'Unauthorized. You cannot assign a dish to a category from another branch.');
            }

            throw ValidationException::withMessages([
                'category_id' => 'The selected category does not belong to the target branch.',
            ]);
        }

        return $category;
    }

    protected function sanitizeRequiredMealName(mixed $value): string
    {
        $sanitized = InputSanitizer::clean($value !== null ? (string) $value : null);
        if ($sanitized === null) {
            throw ValidationException::withMessages([
                'name' => 'Please provide a valid dish name.',
            ]);
        }

        return $sanitized;
    }
}
