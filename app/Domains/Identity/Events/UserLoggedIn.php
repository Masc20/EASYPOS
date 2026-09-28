<?php

namespace App\Domains\Identity\Events;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLoggedIn
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $user
    ) {}
}
