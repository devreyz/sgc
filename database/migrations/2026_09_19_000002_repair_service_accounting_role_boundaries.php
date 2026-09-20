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

    private const FINANCIAL_SERVICE_PERMISSIONS = [
        'view_service_management', 'view_service_financials', 'manage_service_receivables',
        'manage_service_payables', 'record_service_payment', 'reverse_service_payment',
        'manage_service_expenses', 'manage_service_agreements', 'view_service_reports',
        'simulate_services',
    ];

    private const GOVERNANCE_SERVICE_PERMISSIONS = [
        'view_service_portal', 'view_service_management', 'create_service_order',
        'edit_service_order', 'review_service_execution', 'approve_service_execution',
        'view_service_financials', 'manage_service_resources', 'view_service_reports',
        'operate_all_service_orders_portal', 'simulate_services',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $servicePermissions = collect(self::SERVICE_PERMISSIONS)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );

        $this->replaceServicePermissions(['financeiro'], $servicePermissions, self::FINANCIAL_SERVICE_PERMISSIONS);
        $this->replaceServicePermissions(['tesoureiro'], $servicePermissions, self::SERVICE_PERMISSIONS);
        $this->replaceServicePermissions(['presidente', 'vice_presidente'], $servicePermissions, self::GOVERNANCE_SERVICE_PERMISSIONS);
        $this->replaceServicePermissions(['contador'], $servicePermissions, []);
        $this->replaceServicePermissions(
            ['service_provider', 'tratorista', 'motorista', 'diarista', 'tecnico'],
            $servicePermissions,
            ['view_service_portal', 'view_own_service_orders', 'create_own_service_order', 'record_own_service_execution', 'upload_own_service_evidence']
        );

        Role::query()->whereIn('name', ['super_admin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($servicePermissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function replaceServicePermissions(array $roles, $servicePermissions, array $allowed): void
    {
        Role::query()->whereIn('name', $roles)->get()->each(function (Role $role) use ($servicePermissions, $allowed): void {
            $role->revokePermissionTo($servicePermissions);
            $role->givePermissionTo($servicePermissions->whereIn('name', $allowed));
        });
    }

    public function down(): void
    {
        // O rollback não deve restaurar concessões excessivas de acesso.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
