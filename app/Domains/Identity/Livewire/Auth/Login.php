<?php

namespace App\Domains\Identity\Livewire\Auth;

use App\Domains\Identity\Events\UserLoggedIn;
use App\Domains\Identity\Models\User;
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
            $this->pin = '';

            throw ValidationException::withMessages([
                'emp_id' => 'Invalid Employee ID or PIN.',
            ]);
        }

        // Check branch status if assigned
        if ($user->branch && ! $user->branch->is_active) {
            $this->pin = '';
            throw ValidationException::withMessages([
                'emp_id' => 'Your assigned branch is currently inactive.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        Auth::login($user);
        session()->regenerate();

        session(['auth_method' => 'pin']);

        if ($user->branch_id) {
            session(['current_branch_id' => $user->branch_id]);
        }

        event(new UserLoggedIn($user));

        $this->redirectIntended(default: $user->stationRoute());
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

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

    public function render()
    {
        return view('livewire.identity.auth.login');
    }
}
