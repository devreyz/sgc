<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const SERVICE_PERMISSIONS = [
        'view_service_portal', 'view_own_service_orders', 'create_own_service_order',
        'record_own_service_execution', 'upload_own_service_evidence',
        'view_service_management', 'manage_service_catalog', 'manage_service_providers',
        'create_service_order', 'edit_service_order', 'review_service_execution',
        'approve_service_execution', 'view_service_financials', 'manage_service_receivables',
        'manage_service_payables', 'record_service_payment', 'reverse_service_payment',
        'manage_service_resources', 'manage_service_expenses', 'manage_service_agreements',
        'view_service_reports', 'operate_all_service_orders_portal', 'simulate_services',
    ];

    private const ACCOUNTING_PERMISSIONS = [
        'view_accounting_portal', 'view_accounting_processes', 'review_accounting_processes',
        'request_accounting_corrections', 'send_accounting_authorizations',
        'cancel_accounting_authorizations', 'view_accounting_fiscal_queue',
        'prepare_accounting_fiscal', 'view_accounting_fiscal_settings',
        'manage_accounting_fiscal_settings',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $servicePermissions = collect(self::SERVICE_PERMISSIONS)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );
        $treasurer = Role::query()->where('name', 'tesoureiro')->first();
        if ($treasurer) {
            $treasurer->revokePermissionTo($servicePermissions);
            $treasurer->givePermissionTo($servicePermissions);
        }

        $accountingPermissions = collect(self::ACCOUNTING_PERMISSIONS)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );
        Role::query()->where('name', 'financeiro')->get()->each(function (Role $role) use ($accountingPermissions): void {
            $role->revokePermissionTo($accountingPermissions);
        });
        Role::query()->whereIn('name', ['super_admin', 'admin', 'tesoureiro', 'contador'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($accountingPermissions));

        Role::query()->where('name', 'contador')->get()->each(function (Role $role) use ($servicePermissions): void {
            $role->revokePermissionTo($servicePermissions);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
