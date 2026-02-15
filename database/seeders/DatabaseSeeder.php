<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TenantSeeder::class, // Deve ser primeiro para criar tenant
            RolesAndPermissionsSeeder::class,
            ChartAccountSeeder::class,
            ServiceSeeder::class,
        ]);
    }
}
