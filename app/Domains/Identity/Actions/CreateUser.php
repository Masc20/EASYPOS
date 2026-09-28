<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUser
{
    /**
     * Create a new user with optional role assignment.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @param  string|null  $role
     */
    public function handle(array $data, ?string $role = null): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }
}
