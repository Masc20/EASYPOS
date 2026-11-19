<?php

namespace App\Http\Controllers;

use App\Domains\Audit\Services\ActivityLogger;
use App\Domains\Identity\Models\User;
use App\Domains\PointOfSale\Livewire\Checkout\CheckoutCart;
use App\Support\Constants\SystemConstants;
use App\Support\Enums\OrderType;
use App\Support\Enums\StaffRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class TerminalLockController
{
    /**
     * Render the persistent terminal lock screen when locked, or return to station if unlocked.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $request->session()->get(SystemConstants::SESSION_TERMINAL_LOCKED, false)) {
            return redirect()->to($this->resolveSafeReturnUrl(
                $request->session()->get(SystemConstants::SESSION_TERMINAL_LOCK_RETURN_URL),
                $user,
                $request
            ));
        }

        $isFloorContext = $request->session()->get(SystemConstants::SESSION_AUTH_METHOD) === 'pin'
            || $user->isStrictFloorStaff();

        return response()
            ->view('pages.terminal-lock', [
                'user' => $user,
                'lockedAt' => $request->session()->get(SystemConstants::SESSION_TERMINAL_LOCKED_AT),
                'isFloorContext' => $isFloorContext,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    /**
     * Engage the persistent session-backed terminal lock.
     */
    public function lock(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $candidate = $request->input('return_url') ?: $request->headers->get('Referer');
        $returnUrl = $this->resolveSafeReturnUrl(
            is_string($candidate) ? $candidate : null,
            $user,
            $request
        );

        if ($request->has('cart_state')) {
            $rawCartState = $request->input('cart_state');
            $decodedCart = is_string($rawCartState) && $rawCartState !== ''
                ? json_decode($rawCartState, true)
                : (is_array($rawCartState) ? $rawCartState : null);

            if (is_array($decodedCart) && array_key_exists('items', $decodedCart) && is_array($decodedCart['items'])) {
                $cartKey = CheckoutCart::savedCartSessionKey($user->id);

                if (empty($decodedCart['items'])) {
                    $request->session()->forget($cartKey);
                } else {
                    $normalizedItems = [];
                    foreach ($decodedCart['items'] as $item) {
                        if (! is_array($item) || empty($item['meal_id'])) {
                            continue;
                        }
                        $normalizedItems[] = [
                            'meal_id' => (int) $item['meal_id'],
                            'name' => (string) ($item['name'] ?? ''),
                            'menu_code' => isset($item['menu_code']) ? (string) $item['menu_code'] : '',
                            'price' => (float) ($item['price'] ?? 0),
                            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                            'special_instructions' => (string) ($item['special_instructions'] ?? ''),
                            'max_quantity' => isset($item['max_quantity']) && $item['max_quantity'] !== null
                                ? (int) $item['max_quantity']
                                : null,
                        ];
                    }

                    if (! empty($normalizedItems)) {
                        $request->session()->put($cartKey, [
                            'items' => $normalizedItems,
                            'orderType' => is_string($decodedCart['orderType'] ?? null) && $decodedCart['orderType'] !== ''
                                ? $decodedCart['orderType']
                                : OrderType::DINE_IN->value,
                            'tableNumber' => (string) ($decodedCart['tableNumber'] ?? ''),
                            'customerName' => (string) ($decodedCart['customerName'] ?? ''),
                            'discountType' => in_array($decodedCart['discountType'] ?? 'none', ['none', 'senior_pwd', 'custom'], true)
                                ? (string) $decodedCart['discountType']
                                : 'none',
                            'customDiscount' => (float) ($decodedCart['customDiscount'] ?? 0.00),
                            'notes' => (string) ($decodedCart['notes'] ?? ''),
                        ]);
                    }
                }
            }
        }

        $request->session()->put([
            SystemConstants::SESSION_TERMINAL_LOCKED => true,
            SystemConstants::SESSION_TERMINAL_LOCKED_AT => now()->toIso8601String(),
            SystemConstants::SESSION_TERMINAL_LOCKED_BY => $user->id,
            SystemConstants::SESSION_TERMINAL_LOCK_RETURN_URL => $returnUrl,
        ]);

        Log::channel('security')->info('TERMINAL_LOCKED', [
            'user_id' => $user->id,
            'emp_id' => $user->emp_id,
            'branch_id' => $user->branch_id,
            'ip' => $request->ip(),
        ]);

        ActivityLogger::success(
            category: 'security',
            event: 'terminal_locked',
            description: "{$user->name} locked their terminal session.",
            actor: $user,
            branchId: $user->branch_id,
            subject: $user,
        );

        return redirect()->route('terminal.lock.show');
    }

    /**
     * Validate credentials and unlock the locked terminal session.
     */
    public function unlock(Request $request): RedirectResponse
    {
        $lockedUser = $request->user();

        if (! $lockedUser) {
            return redirect()->route('login');
        }

        if (! $lockedUser->is_active || ($lockedUser->branch && ! $lockedUser->branch->is_active)) {
            $isFloor = $request->session()->get(SystemConstants::SESSION_AUTH_METHOD) === 'pin'
                || $lockedUser->isStrictFloorStaff();

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to($isFloor ? route('login') : url('/admin/login'))
                ->withErrors(['emp_id' => 'Your account or assigned branch is currently inactive.']);
        }

        $throttleKey = 'terminal-unlock:' . $lockedUser->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            Log::channel('security')->warning('TERMINAL_UNLOCK_LOCKED_OUT', [
                'locked_user_id' => $lockedUser->id,
                'locked_emp_id' => $lockedUser->emp_id,
                'branch_id' => $lockedUser->branch_id,
                'ip' => $request->ip(),
                'retry_after' => $seconds,
            ]);

            return back()
                ->withInput($request->only('emp_id', 'email', 'unlock_mode'))
                ->withErrors([
                    'credential' => "Too many failed unlock attempts. Please try again in {$seconds} seconds.",
                ]);
        }

        $mode = (string) $request->input('unlock_mode', 'self');
        $empId = strtoupper(trim((string) $request->input('emp_id', '')));
        $email = strtolower(trim((string) $request->input('email', '')));
        $pin = trim((string) $request->input('pin', ''));
        $password = (string) $request->input('password', '');

        $approver = null;
        $authorized = false;

        if ($mode !== 'override' && $empId === '' && $email === '') {
            $pinValid = preg_match('/^[0-9]{4}$/', $pin) === 1 && $lockedUser->verifyPin($pin);
            $passwordValid = $password !== '' && filled($lockedUser->password) && Hash::check($password, $lockedUser->password);

            if ($pinValid || $passwordValid) {
                $authorized = true;
                $approver = $lockedUser;
            }
        } else {
            $approver = $this->findActiveApprover($empId, $email);

            if ($approver && (! $approver->branch || $approver->branch->is_active)) {
                $pinValid = preg_match('/^[0-9]{4}$/', $pin) === 1 && $approver->verifyPin($pin);
                $passwordValid = $password !== '' && filled($approver->password) && Hash::check($password, $approver->password);

                if ($pinValid || $passwordValid) {
                    $isSelf = (int) $approver->id === (int) $lockedUser->id;
                    $isExecutive = $approver->isOwner();
                    $isSameBranchManager = $approver->hasRole(StaffRole::BRANCH_MANAGER->value)
                        && $lockedUser->branch_id !== null
                        && (int) $approver->branch_id === (int) $lockedUser->branch_id;

                    $authorized = $isSelf || $isExecutive || $isSameBranchManager;
                }
            }
        }

        if (! $authorized) {
            RateLimiter::hit($throttleKey, 60);

            Log::channel('security')->warning('TERMINAL_UNLOCK_FAILED', [
                'locked_user_id' => $lockedUser->id,
                'locked_emp_id' => $lockedUser->emp_id,
                'approver_emp_id' => $empId !== '' ? $empId : null,
                'approver_email' => $email !== '' ? $email : null,
                'branch_id' => $lockedUser->branch_id,
                'ip' => $request->ip(),
            ]);

            ActivityLogger::failed(
                category: 'security',
                event: 'terminal_unlock_failed',
                description: "Failed terminal unlock attempt for {$lockedUser->name}.",
                actor: $lockedUser,
                branchId: $lockedUser->branch_id,
                metadata: ['mode' => $mode],
            );

            return back()
                ->withInput($request->only('emp_id', 'email', 'unlock_mode'))
                ->withErrors([
                    'credential' => 'Invalid credentials or insufficient authorization to unlock this terminal.',
                ]);
        }

        RateLimiter::clear($throttleKey);

        $returnUrl = $this->resolveSafeReturnUrl(
            $request->session()->get(SystemConstants::SESSION_TERMINAL_LOCK_RETURN_URL),
            $lockedUser,
            $request
        );

        $request->session()->forget([
            SystemConstants::SESSION_TERMINAL_LOCKED,
            SystemConstants::SESSION_TERMINAL_LOCKED_AT,
            SystemConstants::SESSION_TERMINAL_LOCKED_BY,
            SystemConstants::SESSION_TERMINAL_LOCK_RETURN_URL,
        ]);
        $request->session()->regenerate();

        Log::channel('security')->info('TERMINAL_UNLOCKED', [
            'locked_user_id' => $lockedUser->id,
            'locked_emp_id' => $lockedUser->emp_id,
            'approver_id' => $approver?->id,
            'approver_emp_id' => $approver?->emp_id,
            'branch_id' => $lockedUser->branch_id,
            'ip' => $request->ip(),
        ]);

        ActivityLogger::success(
            category: 'security',
            event: 'terminal_unlocked',
            description: "{$lockedUser->name}'s terminal was unlocked" . ($approver ? " by {$approver->name}." : '.'),
            actor: $approver ?? $lockedUser,
            branchId: $lockedUser->branch_id,
            subject: $lockedUser,
        );

        return redirect()->to($returnUrl);
    }

    /**
     * Find an active approver by normalized employee ID or email.
     */
    protected function findActiveApprover(string $empId, string $email): ?User
    {
        $query = User::query()->where('is_active', true);

        if ($empId !== '') {
            return $query->whereRaw('UPPER(emp_id) = ?', [$empId])->first();
        }

        if ($email !== '') {
            return $query->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        return null;
    }

    /**
     * Resolve a safe same-host internal return path after unlocking.
     */
    protected function resolveSafeReturnUrl(?string $candidate, User $user, Request $request): string
    {
        $isFloorContext = $request->session()->get(SystemConstants::SESSION_AUTH_METHOD) === 'pin'
            || $user->isStrictFloorStaff();

        $fallback = $isFloorContext
            ? $user->stationRoute()
            : ($user->canAccessAdmin() ? url('/admin') : route('dashboard'));

        if (! is_string($candidate) || trim($candidate) === '') {
            return $fallback;
        }

        $candidate = trim($candidate);

        if (str_starts_with($candidate, '//') || str_contains($candidate, '\\')) {
            return $fallback;
        }

        $parsed = parse_url($candidate);

        if ($parsed === false) {
            return $fallback;
        }

        if (isset($parsed['host']) && strcasecmp($parsed['host'], $request->getHost()) !== 0) {
            return $fallback;
        }

        $path = '/' . ltrim((string) ($parsed['path'] ?? '/'), '/');
        $disallowedPrefixes = ['/terminal/', '/terminal', '/logout', '/admin/logout', '/login', '/admin/login', '/livewire/', '/up'];

        foreach ($disallowedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/')) {
                return $fallback;
            }
        }

        if ($isFloorContext && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
            return $user->stationRoute();
        }

        return $path . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
    }
}
