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
        $permission = Permission::firstOrCreate([
            'name' => 'view_delivery_conference_sheets',
            'guard_name' => 'web',
        ]);
        Role::query()->where('name', 'visualizador_entregas')->each(
            fn (Role $role) => $role->givePermissionTo($permission)
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::query()->where('name', 'view_delivery_conference_sheets')->first();
        Role::query()->where('name', 'visualizador_entregas')->each(
            fn (Role $role) => $permission && $role->revokePermissionTo($permission)
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
