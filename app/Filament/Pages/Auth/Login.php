<?php

namespace App\Filament\Pages\Auth;

use App\Domains\Audit\Services\ActivityLogger;
use App\Domains\Identity\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected static string $layout = 'filament-panels::components.layout.base';

    protected bool $deniedByInactiveBranch = false;

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        $this->deniedByInactiveBranch = false;

        if ($user instanceof User && $user->is_active && $user->hasInactiveBranch()) {
            $this->deniedByInactiveBranch = true;

            ActivityLogger::denied(
                category: 'auth',
                event: 'login_failed',
                description: "Staff {$user->email} attempted back-office login on an inactive branch.",
                actor: $user,
                branchId: $user->branch_id,
                metadata: ['reason' => 'branch_inactive'],
            );

            return false;
        }

        return parent::isUserAllowedToAccessPanel($user);
    }

    protected function throwFailureValidationException(): never
    {
        if ($this->deniedByInactiveBranch) {
            throw ValidationException::withMessages([
                'data.email' => 'Your assigned branch is currently inactive. Please contact the system administrator.',
            ]);
        }

        parent::throwFailureValidationException();
    }
}
