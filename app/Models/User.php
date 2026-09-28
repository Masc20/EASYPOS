<?php

namespace App\Models;

use App\Domains\Identity\Models\User as DomainUser;

/**
 * Framework and ecosystem compatibility bridge for the User model.
 * Business logic and domain behavior reside in App\Domains\Identity\Models\User.
 */
class User extends DomainUser
{
    //
}
