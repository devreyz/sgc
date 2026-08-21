<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view_accounting_portal',
        'view_accounting_processes',
        'review_accounting_processes',
        'request_accounting_corrections',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)->map(
            fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ])
        );

        $accountant = Role::firstOrCreate(['name' => 'contador', 'guard_name' => 'web']);

        Role::query()
            ->whereIn('name', ['super_admin', 'admin', 'contador'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        $viewPermissions = $permissions->whereIn('name', [
            'view_accounting_portal',
            'view_accounting_processes',
        ]);

        Role::query()
            ->whereIn('name', ['financeiro', 'tesoureiro', 'presidente'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($viewPermissions));

        $accountant->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
