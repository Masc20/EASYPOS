<?php

namespace App\Support\Services;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Enums\StaffRole;
use Illuminate\Support\Str;

class CodeGeneratorService
{
    /**
     * Generate an Employee ID based on Branch, Position/Role, and auto-increment sequence.
     * Format: {BRANCH}-{ROLE_CODE}-{001} (e.g. B01-CSH-001, HQ-ADM-001).
     */
    public static function generateEmpId(?int $branchId, string $roleName): string
    {
        $branchCode = 'HQ';
        if ($branchId) {
            $branch = Branch::find($branchId);
            if ($branch && filled($branch->code)) {
                $branchCode = Str::upper($branch->code);
            }
        }

        $roleEnum = StaffRole::tryFrom($roleName);
        $rolePrefix = $roleEnum ? $roleEnum->codePrefix() : Str::upper(substr(preg_replace('/[^a-zA-Z]/', '', $roleName), 0, 3) ?: 'STF');

        $prefix = "{$branchCode}-{$rolePrefix}-";

        // Count existing users with matching prefix
        $maxSequence = User::where('emp_id', 'like', "{$prefix}%")
            ->pluck('emp_id')
            ->map(function ($id) use ($prefix) {
                $num = str_replace($prefix, '', $id);
                return is_numeric($num) ? (int) $num : 0;
            })
            ->max() ?? 0;

        $nextNumber = str_pad((string) ($maxSequence + 1), 3, '0', STR_PAD_LEFT);

        return "{$prefix}{$nextNumber}";
    }

    /**
     * Generate a Menu Code based on category name and sequential number.
     * Format: {CAT_PREFIX}-{001} (e.g. BUR-001, BEV-001, MN-001).
     */
    public static function generateMenuCode(?int $categoryId): string
    {
        $prefix = 'ITM';

        if ($categoryId) {
            $category = MealCategory::find($categoryId);
            if ($category && filled($category->name)) {
                $clean = Str::upper(preg_replace('/[^a-zA-Z]/', '', $category->name));
                $prefix = strlen($clean) >= 3 ? substr($clean, 0, 3) : str_pad($clean, 3, 'X');
            }
        }

        $codePrefix = "{$prefix}-";

        $maxSequence = Meal::where('menu_code', 'like', "{$codePrefix}%")
            ->pluck('menu_code')
            ->map(function ($code) use ($codePrefix) {
                $num = str_replace($codePrefix, '', $code);
                return is_numeric($num) ? (int) $num : 0;
            })
            ->max() ?? 0;

        $nextNumber = str_pad((string) ($maxSequence + 1), 3, '0', STR_PAD_LEFT);

        return "{$codePrefix}{$nextNumber}";
    }

    /**
     * Generate a Branch Code from branch name or sequential numbering.
     * Format: 3-4 letters uppercase or B{NN} (e.g. DWTN, B02).
     */
    public static function generateBranchCode(?string $branchName = null): string
    {
        if (filled($branchName)) {
            // Try extracting acronym or first 4 letters
            $words = explode(' ', trim($branchName));
            if (count($words) >= 2) {
                $acronym = Str::upper(substr($words[0], 0, 2) . substr($words[1], 0, 2));
                if (! Branch::where('code', $acronym)->exists()) {
                    return $acronym;
                }
            } else {
                $candidate = Str::upper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $branchName), 0, 4));
                if (strlen($candidate) >= 3 && ! Branch::where('code', $candidate)->exists()) {
                    return $candidate;
                }
            }
        }

        // Sequential fallback: B01, B02, B03...
        $count = Branch::count() + 1;
        $code = 'B' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);

        while (Branch::where('code', $code)->exists()) {
            $count++;
            $code = 'B' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
