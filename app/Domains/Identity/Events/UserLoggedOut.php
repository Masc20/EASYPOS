<?php

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLoggedOut
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ?User $user = null
    ) {}
}
