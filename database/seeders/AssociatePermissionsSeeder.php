<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssociatePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create the associate role (match existing seeder naming)
        $associateRole = Role::firstOrCreate(['name' => 'associado', 'guard_name' => 'web']);

        // Resources that associates can VIEW ONLY
        $viewOnlyResources = [
            'sales_project',
            'production_delivery',
            'associate_ledger',
        ];

        $permissions = [];

        foreach ($viewOnlyResources as $resource) {
            // Create view permission if it doesn't exist
            $viewPermission = Permission::firstOrCreate([
                'name' => "view_{$resource}",
                'guard_name' => 'web',
            ]);

            $viewAnyPermission = Permission::firstOrCreate([
                'name' => "view_any_{$resource}",
                'guard_name' => 'web',
            ]);

            $permissions[] = $viewPermission;
            $permissions[] = $viewAnyPermission;
        }

        // Sync permissions to associate role (removes all other permissions)
        $associateRole->syncPermissions($permissions);

        $this->command->info('Associate permissions configured (read-only access).');
    }
}
