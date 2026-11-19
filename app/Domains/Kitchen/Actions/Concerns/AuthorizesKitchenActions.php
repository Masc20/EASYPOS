<?php

namespace App\Domains\Kitchen\Actions\Concerns;

use App\Domains\Identity\Models\User;
use App\Domains\Kitchen\Models\KitchenTicket;

trait AuthorizesKitchenActions
{
    protected function authorizeKitchenAction(?KitchenTicket $ticket, string $actionDescription): User
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->can('kitchen.prepare')) {
            abort(403, "Unauthorized. Only kitchen staff can {$actionDescription}.");
        }

        if (! $user->isOwner()) {
            $effectiveBranchId = $user->effectiveBranchId();
            if ($ticket === null || $effectiveBranchId === null || $effectiveBranchId < 0 || (int) $ticket->branch_id !== $effectiveBranchId) {
                abort(403, "Unauthorized. You cannot {$actionDescription} for another branch.");
            }
        }

        return $user;
    }
}
