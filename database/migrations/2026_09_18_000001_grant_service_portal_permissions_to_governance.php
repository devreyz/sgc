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
        $permissions = collect([
            'operate_all_service_orders_portal',
            'manage_service_expenses',
            'approve_service_execution',
        ])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        Role::query()->whereIn('name', ['presidente', 'vice_presidente'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissions = Permission::query()->whereIn('name', [
            'operate_all_service_orders_portal',
            'manage_service_expenses',
            'approve_service_execution',
        ])->get();
        Role::query()->whereIn('name', ['presidente', 'vice_presidente'])->get()
            ->each(fn (Role $role) => $role->revokePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
