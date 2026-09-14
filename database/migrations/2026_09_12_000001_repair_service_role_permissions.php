<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const MANAGEMENT = [
        'view_service_management',
        'manage_service_catalog',
        'manage_service_providers',
        'create_service_order',
        'edit_service_order',
        'review_service_execution',
        'approve_service_execution',
        'view_service_financials',
        'manage_service_receivables',
        'manage_service_payables',
        'record_service_payment',
        'reverse_service_payment',
        'manage_service_resources',
        'manage_service_expenses',
        'manage_service_agreements',
        'view_service_reports',
    ];

    private const FINANCIAL = [
        'view_service_management',
        'view_service_financials',
        'manage_service_receivables',
        'manage_service_payables',
        'record_service_payment',
        'reverse_service_payment',
        'view_service_reports',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $management = collect(self::MANAGEMENT)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']))
            ->all();
        $financial = Permission::query()->whereIn('name', self::FINANCIAL)->get()->all();

        Role::query()->whereIn('name', ['super_admin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($management));
        Role::query()->whereIn('name', ['financeiro', 'tesoureiro'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($financial));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // As permissões podem ter sido atribuídas deliberadamente pelo Shield.
        // O rollback não remove decisões administrativas de papéis globais.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
