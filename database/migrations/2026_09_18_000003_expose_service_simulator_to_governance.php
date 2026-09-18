<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $simulate = Permission::firstOrCreate(['name' => 'simulate_services', 'guard_name' => 'web']);
        $view = Permission::firstOrCreate(['name' => 'view_service_management', 'guard_name' => 'web']);
        Role::query()->whereIn('name', [
            'super_admin', 'admin', 'presidente', 'vice_presidente', 'tesoureiro', 'financeiro',
        ])->get()->each(fn (Role $role) => $role->givePermissionTo([$simulate, $view]));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $simulate = Permission::query()->where('name', 'simulate_services')->first();
        if ($simulate) {
            Role::query()->whereIn('name', ['presidente', 'vice_presidente', 'tesoureiro', 'financeiro'])
                ->get()->each(fn (Role $role) => $role->revokePermissionTo($simulate));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
