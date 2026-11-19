<?php

namespace App\Domains\Identity\Livewire\Auth;

use App\Domains\Audit\Services\ActivityLogger;
use App\Domains\Identity\Events\UserLoggedIn;
use App\Domains\Identity\Models\User;
use App\Support\Constants\SystemConstants;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Login extends Component
{
    #[Validate('required|string|min:3')]
    public string $emp_id = '';

    #[Validate('required|string|size:4|regex:/^[0-9]{4}$/')]
    public string $pin = '';

    /**
     * Append a numeric digit to the PIN (from touch keypad).
     */
    public function appendDigit(string $digit): void
    {
        if (strlen($this->pin) < 4) {
            $this->pin .= $digit;

            // Auto-submit when all 4 digits are entered and Employee ID is present
            if (strlen($this->pin) === 4 && filled($this->emp_id)) {
                $this->login();
            }
        }
    }

    /**
     * Remove the last entered PIN digit.
     */
    public function backspace(): void
    {
        $this->pin = substr($this->pin, 0, -1);
    }

    /**
     * Clear all PIN digits.
     */
    public function clearPin(): void
    {
        $this->pin = '';
    }

    /**
     * Handle incoming floor staff authentication.
     */
    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        $user = User::where('emp_id', trim($this->emp_id))->first();

        if (! $user || ! $user->verifyPin($this->pin)) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->globalThrottleKey());
            $this->pin = '';

            ActivityLogger::failed(
                category: 'auth',
                event: 'login_failed',
                description: 'Failed floor terminal login attempt for Employee ID ' . strtoupper(trim($this->emp_id)) . '.',
                actor: $user,
                branchId: $user?->branch_id,
                metadata: ['emp_id' => strtoupper(trim($this->emp_id)), 'reason' => 'invalid_credentials'],
                actorName: $user?->name ?? strtoupper(trim($this->emp_id)),
                actorEmpId: $user?->emp_id ?? strtoupper(trim($this->emp_id)),
            );

            throw ValidationException::withMessages([
                'emp_id' => 'Invalid Employee ID or PIN.',
            ]);
        }

        // Check if user account is deactivated
        if (! $user->is_active) {
            $this->pin = '';
            ActivityLogger::denied(
                category: 'auth',
                event: 'login_failed',
                description: "Deactivated staff account {$user->emp_id} attempted floor terminal login.",
                actor: $user,
                branchId: $user->branch_id,
                metadata: ['reason' => 'account_deactivated'],
            );
            throw ValidationException::withMessages([
                'emp_id' => 'Your account has been deactivated. Please contact your manager or system administrator.',
            ]);
        }

        // Check branch status if assigned
        if ($user->branch && ! $user->branch->is_active) {
            $this->pin = '';
            ActivityLogger::denied(
                category: 'auth',
                event: 'login_failed',
                description: "Staff {$user->emp_id} attempted login on inactive branch.",
                actor: $user,
                branchId: $user->branch_id,
                metadata: ['reason' => 'branch_inactive'],
            );
            throw ValidationException::withMessages([
                'emp_id' => 'Your assigned branch is currently inactive.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->globalThrottleKey());

        Auth::login($user);
        session()->regenerate();

        session([SystemConstants::SESSION_AUTH_METHOD => 'pin']);

        if ($user->branch_id) {
            session([SystemConstants::SESSION_CURRENT_BRANCH => $user->branch_id]);
        }

        event(new UserLoggedIn($user));

        session()->forget([
            'url.intended',
            SystemConstants::SESSION_TERMINAL_LOCKED,
            SystemConstants::SESSION_TERMINAL_LOCKED_AT,
            SystemConstants::SESSION_TERMINAL_LOCKED_BY,
            SystemConstants::SESSION_TERMINAL_LOCK_RETURN_URL,
        ]);

        $this->redirect($user->stationRoute());
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        $ipLimited = RateLimiter::tooManyAttempts($this->throttleKey(), 5);
        $empLimited = RateLimiter::tooManyAttempts($this->globalThrottleKey(), 5);

        if (! $ipLimited && ! $empLimited) {
            return;
        }

        event(new Lockout(request()));

        $seconds = max(
            $ipLimited ? RateLimiter::availableIn($this->throttleKey()) : 0,
            $empLimited ? RateLimiter::availableIn($this->globalThrottleKey()) : 0,
        );

        throw ValidationException::withMessages([
            'emp_id' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Rate limiting throttle key based on IP and employee ID.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->emp_id) . '|' . request()->ip());
    }

    /**
     * Global rate limiting throttle key per employee ID across rotating IPs.
     */
    protected function globalThrottleKey(): string
    {
        return 'floor-login-emp:' . Str::transliterate(Str::lower(trim($this->emp_id)));
    }

    public function render()
    {
        return view('livewire.identity.auth.login');
    }
}
