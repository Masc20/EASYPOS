<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'The default seeder contains demo branches, users, PINs, and menu data. Use ProductionBootstrapSeeder to create the initial production owner safely.'
            );
        }

        $this->call([
            RolesAndPermissionsSeeder::class,
            MenuSeeder::class,
        ]);
    }
}
