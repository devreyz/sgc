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

        $view = Permission::firstOrCreate(['name' => 'view_secretary_portal', 'guard_name' => 'web']);
        $manage = Permission::firstOrCreate(['name' => 'manage_secretary_documents', 'guard_name' => 'web']);
        $secretary = Role::firstOrCreate(['name' => 'secretario', 'guard_name' => 'web']);
        $secretary->syncPermissions([$view, $manage]);

        Role::query()
            ->whereIn('name', ['super_admin', 'admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$view, $manage]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->where('name', 'secretario')->delete();
        Permission::query()
            ->whereIn('name', ['view_secretary_portal', 'manage_secretary_documents'])
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
