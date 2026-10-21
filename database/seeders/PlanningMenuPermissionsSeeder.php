<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PlanningMenuPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Assign explicitly through tenant role administration, never to all recorders.
        Permission::firstOrCreate(['name' => 'approve_menus', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
