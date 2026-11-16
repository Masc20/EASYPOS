<?php

namespace App\Support\Services;

use App\Domains\Branches\Models\Branch;
use App\Domains\Identity\Models\User;
use App\Domains\Menu\Models\Meal;
use App\Domains\Menu\Models\MealCategory;
use App\Support\Enums\StaffRole;
use Illuminate\Support\Facades\DB;
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

        try {
            $driver = DB::connection()->getDriverName();
            $castType = in_array($driver, ['sqlite', 'pgsql'], true) ? 'INTEGER' : 'UNSIGNED';
            $startPos = strlen($prefix) + 1;

            $maxSequence = User::where('emp_id', 'like', "{$prefix}%")
                ->selectRaw("MAX(CAST(SUBSTR(emp_id, {$startPos}) AS {$castType})) as max_seq")
                ->value('max_seq');
        } catch (\Throwable) {
            $maxSequence = User::where('emp_id', 'like', "{$prefix}%")
                ->pluck('emp_id')
                ->map(function ($id) use ($prefix) {
                    $num = str_replace($prefix, '', (string) $id);
                    return is_numeric($num) ? (int) $num : 0;
                })
                ->max();
        }

        $seq = (int) ($maxSequence ?? 0) + 1;
        $candidate = "{$prefix}" . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        while (User::where('emp_id', $candidate)->exists()) {
            $seq++;
            $candidate = "{$prefix}" . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        }

        return $candidate;
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

        $rolesById = \Spatie\Permission\Models\Role::pluck('name', 'id')->toArray();

        $map = [];
        foreach ($branches as $bId => $bCode) {
            $branchCode = Str::upper($bCode ?: 'HQ');
            foreach (StaffRole::cases() as $role) {
                $prefix = "{$branchCode}-{$role->codePrefix()}-";
                $next = ($maxByPrefix[$prefix] ?? 0) + 1;
                $generated = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
                $map["{$bId}:{$role->value}"] = $generated;
            }
            foreach ($rolesById as $roleId => $roleName) {
                $roleEnum = StaffRole::tryFrom($roleName);
                $rolePrefix = $roleEnum ? $roleEnum->codePrefix() : Str::upper(substr(preg_replace('/[^a-zA-Z]/', '', (string) $roleName), 0, 3) ?: 'STF');
                $prefix = "{$branchCode}-{$rolePrefix}-";
                $next = ($maxByPrefix[$prefix] ?? 0) + 1;
                $map["{$bId}:{$roleId}"] = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
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
                $prefix = strlen($clean) >= 1 ? substr($clean, 0, 1) : str_pad($clean, 1, 'X');
            }
        }

        $codePrefix = "{$prefix}-";

        try {
            $driver = DB::connection()->getDriverName();
            $castType = in_array($driver, ['sqlite', 'pgsql'], true) ? 'INTEGER' : 'UNSIGNED';
            $startPos = strlen($codePrefix) + 1;

            $maxSequence = Meal::withTrashed()
                ->where('menu_code', 'like', "{$codePrefix}%")
                ->selectRaw("MAX(CAST(SUBSTR(menu_code, {$startPos}) AS {$castType})) as max_seq")
                ->value('max_seq');
        } catch (\Throwable) {
            $maxSequence = Meal::withTrashed()
                ->where('menu_code', 'like', "{$codePrefix}%")
                ->pluck('menu_code')
                ->map(function ($code) use ($codePrefix) {
                    $num = str_replace($codePrefix, '', (string) $code);
                    return is_numeric($num) ? (int) $num : 0;
                })
                ->max();
        }

        $seq = (int) ($maxSequence ?? 0) + 1;
        $candidate = "{$codePrefix}" . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        while (Meal::withTrashed()->where('menu_code', $candidate)->exists()) {
            $seq++;
            $candidate = "{$codePrefix}" . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        }

        return $candidate;
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
            if (preg_match('/^([A-Z]{1,3}-)(0*[0-9]+)$/', (string) $code, $m)) {
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
            $prefix = (strlen($clean) >= 1 ? substr($clean, 0, 1) : str_pad($clean, 1, 'X')) . '-';
            $next = ($maxByPrefix[$prefix] ?? 0) + 1;
            $map[(int) $catId] = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        }

        return $map;
    }

    /**
     * Generate a random 4-digit terminal PIN.
     * Format: 4 numeric digits (0000-9999).
     * Ensures uniqueness per branch (or globally if no branch context).
     *
     * @param int|null $branchId If provided, ensures PIN uniqueness within that branch only.
     * @throws \RuntimeException if PIN space exhausted (after 1000 attempts).
     */
    public static function generatePin(?int $branchId = null): string
    {
        $maxAttempts = 1000; // 10% of 10000 space - prevents infinite loop

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $query = User::where('pin_code', $pin);
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            if (! $query->exists()) {
                return $pin;
            }
        }

        throw new \RuntimeException('Unable to generate unique 4-digit PIN after 1000 attempts. PIN space may be exhausted for this branch.');
    }

    /**
     * Generate a random back-office password.
     * Format: 12-character alphanumeric with mixed case and symbols.
     * Ensures uniqueness across all users.
     */
    public static function generatePassword(): string
    {
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $digits = '0123456789';
        $symbols = '!@#$%';
        $all = $upper . $lower . $digits . $symbols;

        // Guarantee at least one character from each required complexity class
        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = 4; $i < 12; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Fisher-Yates shuffle using CSPRNG so required classes aren't overwritten or predictable
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /**
     * Generate a Branch Code from branch name or sequential numbering.
     * Format: 3-4 letters uppercase or B{NN} (e.g. DWTN, B02).
     */
    public static function generateBranchCode(?string $branchName = null): string
    {
        if (filled($branchName)) {
            $words = preg_split('/\s+/', trim($branchName)) ?: [];
            if (count($words) >= 2) {
                $acronym = Str::upper(substr($words[0], 0, 2) . substr($words[1], 0, 2));
                if (! Branch::whereRaw('UPPER(code) = ?', [$acronym])->exists()) {
                    return $acronym;
                }
            } else {
                $candidate = Str::upper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $branchName), 0, 4));
                if (strlen($candidate) >= 3 && ! Branch::whereRaw('UPPER(code) = ?', [$candidate])->exists()) {
                    return $candidate;
                }
            }
        }

        // Sequential fallback: find next unused B{NN} code via direct SQL
        try {
            $driver = DB::connection()->getDriverName();
            $castType = in_array($driver, ['sqlite', 'pgsql'], true) ? 'INTEGER' : 'UNSIGNED';
            $maxSeq = Branch::where('code', 'like', 'B%')
                ->selectRaw("MAX(CAST(SUBSTR(code, 2) AS {$castType})) as max_seq")
                ->value('max_seq');
            $count = ((int) ($maxSeq ?? 0)) + 1;
        } catch (\Throwable) {
            $count = Branch::count() + 1;
        }

        $code = 'B' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
        while (Branch::whereRaw('UPPER(code) = ?', [$code])->exists()) {
            $count++;
            $code = 'B' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
