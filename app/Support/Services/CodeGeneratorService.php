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
            $code = Branch::whereKey($branchId)->value('code');
            if (filled($code)) {
                $branchCode = Str::upper($code);
            }
        }

        $roleEnum = StaffRole::tryFrom($roleName);
        $rolePrefix = $roleEnum ? $roleEnum->codePrefix() : Str::upper(substr(preg_replace('/[^a-zA-Z]/', '', $roleName), 0, 3) ?: 'STF');

        $prefix = "{$branchCode}-{$rolePrefix}-";

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
     * Precompute a map of "branchId:role" => next Emp ID in 2 lightweight queries
     * so client-side Alpine.js can update the Employee ID in 0ms without server round-trips.
     *
     * @return array<string, string>
     */
    public static function getEmpIdPreviewMap(): array
    {
        $branches = Branch::pluck('code', 'id')->toArray();
        $branches[0] = 'HQ';

        $existingIds = User::pluck('emp_id')->toArray();
        $maxByPrefix = [];

        foreach ($existingIds as $id) {
            if (preg_match('/^([A-Z0-9]+-[A-Z0-9]+-)(0*[0-9]+)$/', (string) $id, $m)) {
                $p = $m[1];
                $num = (int) $m[2];
                if (! isset($maxByPrefix[$p]) || $num > $maxByPrefix[$p]) {
                    $maxByPrefix[$p] = $num;
                }
            }
        }

        $map = [];
        foreach ($branches as $bId => $bCode) {
            $branchCode = Str::upper($bCode ?: 'HQ');
            foreach (StaffRole::cases() as $role) {
                $prefix = "{$branchCode}-{$role->codePrefix()}-";
                $next = ($maxByPrefix[$prefix] ?? 0) + 1;
                $map["{$bId}:{$role->value}"] = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            }
        }

        return $map;
    }

    /**
     * Generate a Menu Code based on category name and sequential number.
     * Format: {CAT_PREFIX}-{001} (e.g. BUR-001, BEV-001, MN-001).
     */
    public static function generateMenuCode(?int $categoryId): string
    {
        $prefix = 'ITM';

        if ($categoryId) {
            $categoryName = MealCategory::whereKey($categoryId)->value('name');
            if (filled($categoryName)) {
                $clean = Str::upper(preg_replace('/[^a-zA-Z]/', '', $categoryName));
                $prefix = strlen($clean) >= 3 ? substr($clean, 0, 3) : str_pad($clean, 3, 'X');
            }
        }

        $codePrefix = "{$prefix}-";

        $maxSequence = Meal::withTrashed()
            ->where('menu_code', 'like', "{$codePrefix}%")
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
     * Precompute a map of categoryId => next Menu Code in 2 lightweight queries
     * so client-side Alpine.js can update the Menu Code in 0ms without server round-trips.
     *
     * @return array<int, string>
     */
    public static function getCategoryMenuCodeMap(): array
    {
        $categories = MealCategory::pluck('name', 'id')->toArray();
        $existingCodes = Meal::withTrashed()->whereNotNull('menu_code')->pluck('menu_code')->toArray();

        $maxByPrefix = [];
        foreach ($existingCodes as $code) {
            if (preg_match('/^([A-Z]{3}-)(0*[0-9]+)$/', (string) $code, $m)) {
                $p = $m[1];
                $num = (int) $m[2];
                if (! isset($maxByPrefix[$p]) || $num > $maxByPrefix[$p]) {
                    $maxByPrefix[$p] = $num;
                }
            }
        }

        $map = [];
        foreach ($categories as $catId => $catName) {
            $clean = Str::upper(preg_replace('/[^a-zA-Z]/', '', (string) $catName));
            $prefix = (strlen($clean) >= 3 ? substr($clean, 0, 3) : str_pad($clean, 3, 'X')) . '-';
            $next = ($maxByPrefix[$prefix] ?? 0) + 1;
            $map[(int) $catId] = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        }

        return $map;
    }

    /**
     * Generate a Branch Code from branch name or sequential numbering.
     * Format: 3-4 letters uppercase or B{NN} (e.g. DWTN, B02).
     */
    public static function generateBranchCode(?string $branchName = null): string
    {
        $existingCodes = array_flip(Branch::pluck('code')->map(fn ($c) => Str::upper((string) $c))->toArray());

        if (filled($branchName)) {
            $words = preg_split('/\s+/', trim($branchName)) ?: [];
            if (count($words) >= 2) {
                $acronym = Str::upper(substr($words[0], 0, 2) . substr($words[1], 0, 2));
                if (! isset($existingCodes[$acronym])) {
                    return $acronym;
                }
            } else {
                $candidate = Str::upper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $branchName), 0, 4));
                if (strlen($candidate) >= 3 && ! isset($existingCodes[$candidate])) {
                    return $candidate;
                }
            }
        }

        $count = count($existingCodes) + 1;
        $code = 'B' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);

        while (isset($existingCodes[$code])) {
            $count++;
            $code = 'B' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
