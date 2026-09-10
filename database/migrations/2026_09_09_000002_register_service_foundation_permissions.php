<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view_service_portal', 'view_own_service_orders', 'create_own_service_order',
        'record_own_service_execution', 'upload_own_service_evidence',
        'view_service_management', 'manage_service_catalog', 'manage_service_providers',
        'create_service_order', 'edit_service_order', 'review_service_execution',
        'approve_service_execution', 'view_service_financials', 'manage_service_receivables',
        'manage_service_payables', 'record_service_payment', 'reverse_service_payment',
        'manage_service_resources', 'manage_service_expenses', 'manage_service_agreements',
        'view_service_reports',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(self::PERMISSIONS)->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()->whereIn('name', ['super_admin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));
        Role::query()->whereIn('name', ['financeiro', 'tesoureiro'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->filter(
                fn (Permission $permission) => str_contains($permission->name, 'financial')
                    || str_contains($permission->name, 'payment')
                    || str_contains($permission->name, 'receivable')
                    || str_contains($permission->name, 'payable')
                    || $permission->name === 'view_service_management'
                    || $permission->name === 'view_service_reports'
            )));
        Role::query()->whereIn('name', ['service_provider', 'tratorista', 'motorista', 'diarista', 'tecnico'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->whereIn('name', [
                'view_service_portal', 'view_own_service_orders', 'create_own_service_order',
                'record_own_service_execution', 'upload_own_service_evidence',
            ])));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
