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
        $permissions = collect(['view_pre_distributions', 'record_pre_distributions', 'manage_pre_distributions'])
            ->map(fn ($name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        Role::firstOrCreate(['name' => 'entregador', 'guard_name' => 'web'])->givePermissionTo(
            $permissions->whereIn('name', ['view_pre_distributions', 'record_pre_distributions'])
        );
        foreach (['admin', 'registrador_entregas'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->givePermissionTo($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep role assignments and permissions as inert history; the schema rollback refuses to erase used records.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
