<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToggleUserActiveStatusAction
{
    /**
     * Toggle a user's active/deactivated state with self-lockout validation and session revocation.
     *
     * @throws ValidationException
     */
    public function execute(User $user, ?User $actor = null): User
    {
        $actorId = $actor?->id ?? auth()->id();

        // Prevent self-deactivation
        if ($user->is_active && $actorId && (int) $user->id === (int) $actorId) {
            throw ValidationException::withMessages([
                'user' => 'You cannot deactivate your own account.',
            ]);
        }

        // Toggle state
        $user->is_active = ! (bool) $user->is_active;
        $user->save();

        // When deactivating, revoke active sessions across terminals
        if (! $user->is_active) {
            $this->revokeUserSessions($user);
        }

        return $user;
    }

    /**
     * Terminate active database sessions for the given user.
     */
    protected function revokeUserSessions(User $user): void
    {
        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable $e) {
            // Fail silently if sessions table is unavailable or using alternative driver
        }
    }
}
