<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) env('BOOTSTRAP_OWNER_NAME'));
        $email = trim((string) env('BOOTSTRAP_OWNER_EMAIL'));
        $password = (string) env('BOOTSTRAP_OWNER_PASSWORD');

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($password) < 16) {
            throw new \RuntimeException(
                'Set BOOTSTRAP_OWNER_NAME, a valid BOOTSTRAP_OWNER_EMAIL, and a BOOTSTRAP_OWNER_PASSWORD of at least 16 characters for this one-time seeding command.'
            );
        }

        if (User::where('email', $email)->exists()) {
            throw new \RuntimeException('That email already belongs to an account. Choose a new owner email, no account was changed.');
        }

        DB::transaction(function () use ($name, $email, $password): void {
            $this->call(AccessControlSeeder::class);

            $owner = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'branch_id' => null,
                'is_active' => true,
            ]);

            $owner->forceFill(['email_verified_at' => now()])->save();
            $owner->assignRole(Role::findByName('owner', 'web'));
        });

        $this->command?->info('Production access roles and the initial owner account were created. Create the real branch and menu from the application.');
    }
}
